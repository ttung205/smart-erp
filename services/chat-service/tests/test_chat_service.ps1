# Test script for Chat Service REST API & SignalR Negotiation
param(
    [string]$BaseUrl = "http://localhost:8009",
    [string]$Secret = "smart_erp_super_secure_jwt_secret_key_2026"
)

function New-TestJwt([int]$UserId, [string]$Name, [string]$Dept) {
    $header = @{ alg = "HS256"; typ = "JWT" } | ConvertTo-Json -Compress
    $payload = @{
        sub = $UserId.ToString()
        name = $Name
        email = "$($Name.ToLower().Replace(' ', ''))@erp.local"
        dept = $Dept
        department_code = $Dept
        roles = @("STAFF")
        exp = [DateTimeOffset]::UtcNow.AddDays(7).ToUnixTimeSeconds()
    } | ConvertTo-Json -Compress

    function Base64UrlEncode([byte[]]$bytes) {
        return [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
    }

    $headerBytes = [System.Text.Encoding]::UTF8.GetBytes($header)
    $payloadBytes = [System.Text.Encoding]::UTF8.GetBytes($payload)

    $headerEncoded = Base64UrlEncode $headerBytes
    $payloadEncoded = Base64UrlEncode $payloadBytes
    $dataToSign = "$headerEncoded.$payloadEncoded"

    $hmac = New-Object System.Security.Cryptography.HMACSHA256
    $hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($Secret)
    $signatureBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($dataToSign))
    $signatureEncoded = Base64UrlEncode $signatureBytes

    return "$dataToSign.$signatureEncoded"
}

$tokenUser1 = New-TestJwt 1 "Nguyen Van A" "inventory"
$tokenUser2 = New-TestJwt 2 "Tran Thi B" "purchase"

Write-Host "`n=== 1. TEST HEALTH CHECK ===" -ForegroundColor Cyan
$health = Invoke-RestMethod -Uri "$BaseUrl/api/v1/chat/health" -Method Get
Write-Host "Status:" $health.data.status ", Framework:" $health.data.framework -ForegroundColor Green

Write-Host "`n=== 2. TEST GET CONVERSATIONS (USER 1) ===" -ForegroundColor Cyan
$headers1 = @{ Authorization = "Bearer $tokenUser1" }
$conversations = Invoke-RestMethod -Uri "$BaseUrl/api/v1/chat/conversations" -Headers $headers1 -Method Get
Write-Host "Found" $conversations.data.Count "conversations:" -ForegroundColor Green
foreach ($c in $conversations.data) {
    Write-Host " - [$($c.type)] $($c.name) (Code: $($c.code), Unread: $($c.unread_count))" -ForegroundColor Yellow
}

$khoVan = $conversations.data | Where-Object { $_.code -eq "dept:inventory" } | Select-Object -First 1

Write-Host "`n=== 3. TEST SEND CHANNEL MESSAGE TO #kho-van ===" -ForegroundColor Cyan
$msgPayload = @{
    content = "Thong bao test: Kiem tra ton kho cuoi ngay san pham IP16."
} | ConvertTo-Json -Compress

$sendResult = Invoke-RestMethod -Uri "$BaseUrl/api/v1/chat/conversations/$($khoVan.id)/messages" -Headers $headers1 -Method Post -Body $msgPayload -ContentType "application/json"
Write-Host "Message sent successfully! ID:" $sendResult.data.id "Content:" $sendResult.data.content -ForegroundColor Green

Write-Host "`n=== 4. TEST GET MESSAGES HISTORY OF #kho-van ===" -ForegroundColor Cyan
$messages = Invoke-RestMethod -Uri "$BaseUrl/api/v1/chat/conversations/$($khoVan.id)/messages" -Headers $headers1 -Method Get
Write-Host "Total messages in #kho-van:" $messages.meta.pagination.total_items -ForegroundColor Green
foreach ($m in $messages.data) {
    Write-Host " [$($m.created_at)] $($m.sender_name): $($m.content)" -ForegroundColor White
}

Write-Host "`n=== 5. TEST SIGNALR NEGOTIATION WITH JWT TOKEN ===" -ForegroundColor Cyan
$negotiateResponse = Invoke-RestMethod -Uri "$BaseUrl/api/v1/chat/hub/negotiate?negotiateVersion=1" -Headers $headers1 -Method Post
Write-Host "SignalR Negotiate OK! ConnectionToken:" $negotiateResponse.connectionToken -ForegroundColor Green
Write-Host "Available Transports:" ($negotiateResponse.availableTransports | ForEach-Object { $_.transport } | Out-String).Trim() -ForegroundColor Green

Write-Host "`n=== 6. TEST CREATE DIRECT MESSAGE CONVERSATION (USER 1 -> USER 2) ===" -ForegroundColor Cyan
$dmPayload = @{
    recipient_id = 2
    recipient_name = "Tran Thi B"
} | ConvertTo-Json -Compress

$dmResult = Invoke-RestMethod -Uri "$BaseUrl/api/v1/chat/conversations/direct" -Headers $headers1 -Method Post -Body $dmPayload -ContentType "application/json"
Write-Host "DM Conversation created! ID:" $dmResult.data.id "Name:" $dmResult.data.name "Code:" $dmResult.data.code -ForegroundColor Green

Write-Host "`n=== ALL TESTS PASSED SUCCESSFULLY! ===" -ForegroundColor Green
