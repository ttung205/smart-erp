<#
.SYNOPSIS
    Xem và đẩy lại message trong hàng đợi dead letter (erp.dead_letters) qua RabbitMQ Management API.

.DESCRIPTION
    Message vào erp.dead_letters khi consumer đã thử lại hết số lần (retry 10s x 5) hoặc message hỏng.
    Mặc định chỉ LIỆT KÊ (không lấy message ra khỏi hàng đợi).
    Với -Replay: lấy từng message ra và publish lại đúng chỗ nó bị loại (exchange + routing key trong header
    x-death), xoá header x-death / x-retry-count để consumer thử lại từ đầu. Consumer idempotent theo event_id
    nên đẩy lại một event đã xử lý cũng không tạo bản ghi trùng.
    Chỉ chạy -Replay sau khi đã sửa nguyên nhân lỗi (xem dashboard Logs trên Grafana).

.EXAMPLE
    ./scripts/replay-dead-letters.ps1                 # liệt kê tối đa 50 message
    ./scripts/replay-dead-letters.ps1 -Replay -Count 10
#>
[CmdletBinding()]
param(
    [switch]$Replay,
    [int]$Count = 50,
    [string]$ManagementUrl = "http://127.0.0.1:15672",
    [string]$User = $(if ($env:RABBITMQ_USER) { $env:RABBITMQ_USER } else { "erp_rabbit" }),
    [string]$Password = $(if ($env:RABBITMQ_PASSWORD) { $env:RABBITMQ_PASSWORD } else { "rabbit_secret" }),
    [string]$Queue = "erp.dead_letters"
)

$ErrorActionPreference = "Stop"
$auth = "Basic " + [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${User}:${Password}"))
$headers = @{ Authorization = $auth }
$vhost = "%2F"

function Invoke-Rabbit([string]$Method, [string]$Path, $Body) {
    $json = $Body | ConvertTo-Json -Depth 20 -Compress
    Invoke-RestMethod -Method $Method -Uri "$ManagementUrl/api/$Path" -Headers $headers -ContentType "application/json" -Body ([Text.Encoding]::UTF8.GetBytes($json))
}

function Get-DeathInfo($message) {
    $death = $message.properties.headers.'x-death' | Select-Object -First 1
    if (-not $death) { return $null }
    [pscustomobject]@{
        Exchange   = if ([string]::IsNullOrEmpty($death.exchange)) { "amq.default" } else { $death.exchange }
        RoutingKey = $death.'routing-keys' | Select-Object -First 1
        Queue      = $death.queue
        Reason     = $death.reason
    }
}

$queueInfo = Invoke-RestMethod -Uri "$ManagementUrl/api/queues/$vhost/$Queue" -Headers $headers
Write-Host "$Queue đang có $($queueInfo.messages) message." -ForegroundColor Cyan
if ($queueInfo.messages -eq 0) { return }

# Liệt kê: ack_requeue_true trả message lại hàng đợi ngay sau khi đọc
$ackMode = if ($Replay) { "ack_requeue_false" } else { "ack_requeue_true" }
$messages = Invoke-Rabbit POST "queues/$vhost/$Queue/get" @{ count = $Count; ackmode = $ackMode; encoding = "auto"; truncate = 50000 }

$replayed = 0
foreach ($message in $messages) {
    $death = Get-DeathInfo $message
    $eventType = try { ($message.payload | ConvertFrom-Json).event_type } catch { "(payload không phải JSON)" }
    $target = if ($death) { "$($death.Exchange) / $($death.RoutingKey)" } else { "(không có x-death)" }
    Write-Host ("- {0}  từ queue {1} ({2})  -> {3}" -f $eventType, $death.Queue, $death.Reason, $target)

    if (-not $Replay) { continue }
    if (-not $death) {
        Write-Warning "  Không xác định được nơi gửi lại (thiếu x-death), trả message về $Queue."
        $death = [pscustomobject]@{ Exchange = "amq.default"; RoutingKey = $Queue }
    }

    $properties = @{}
    $message.properties.PSObject.Properties | ForEach-Object { $properties[$_.Name] = $_.Value }
    if ($properties.headers) {
        $cleanHeaders = @{}
        $properties.headers.PSObject.Properties | Where-Object { $_.Name -notin @("x-death", "x-retry-count", "x-first-death-exchange", "x-first-death-queue", "x-first-death-reason", "x-last-death-exchange", "x-last-death-queue", "x-last-death-reason") } |
            ForEach-Object { $cleanHeaders[$_.Name] = $_.Value }
        $properties.headers = $cleanHeaders
    }

    $body = @{ properties = $properties; routing_key = $death.RoutingKey; payload = $message.payload; payload_encoding = $message.payload_encoding }
    try {
        $result = Invoke-Rabbit POST "exchanges/$vhost/$($death.Exchange)/publish" $body
        if (-not $result.routed) { throw "Không có queue nào nhận routing key $($death.RoutingKey)." }
        $replayed++
    } catch {
        # Không để mất message: trả về hàng đợi dead letter
        Write-Warning "  Gửi lại lỗi ($($_.Exception.Message)); trả message về $Queue."
        Invoke-Rabbit POST "exchanges/$vhost/amq.default/publish" @{ properties = $message.properties; routing_key = $Queue; payload = $message.payload; payload_encoding = $message.payload_encoding } | Out-Null
    }
}

if ($Replay) {
    Write-Host "Đã đẩy lại $replayed/$($messages.Count) message." -ForegroundColor Green
} else {
    Write-Host "Chế độ xem. Thêm -Replay để đẩy lại sau khi đã sửa nguyên nhân lỗi." -ForegroundColor Yellow
}
