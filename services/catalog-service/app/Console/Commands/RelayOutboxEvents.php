<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMqPublisher;
use App\Messaging\UnroutableMessageException;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Quét outbox_events và đẩy lên RabbitMQ (at-least-once; consumer tự loại trùng theo event_id).
 *
 * Không bao giờ bỏ event: broker lỗi thì dừng lượt (giữ thứ tự) và chờ giãn dần tới MAX_BACKOFF_MS,
 * broker sống lại thì gửi tiếp từ event đang lỗi. Event không có queue nào nhận (lỗi cấu hình binding)
 * được thử lại thưa dần nhưng không chặn các event phía sau.
 */
class RelayOutboxEvents extends Command
{
    /** Thời gian chờ tối đa giữa hai lượt khi broker lỗi liên tục. */
    public const MAX_BACKOFF_MS = 60_000;

    protected $signature = 'outbox:relay {--once : Chỉ quét một lượt rồi thoát} {--sleep=1500 : Thời gian nghỉ giữa các lượt (ms)}';

    protected $description = 'Publish các domain event đang chờ trong outbox_events lên RabbitMQ';

    private bool $brokerFailed = false;

    public function handle(RabbitMqPublisher $publisher): int
    {
        // Mỗi service chỉ chạy 1 relay để giữ thứ tự publish
        $lock = fopen(storage_path('framework/outbox-relay.lock'), 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Outbox relay đã chạy ở tiến trình khác, bỏ qua.');

            return self::SUCCESS;
        }

        $sleepMs = max(200, (int) $this->option('sleep'));
        $consecutiveBrokerFailures = 0;
        $this->info('Outbox relay đã khởi động.');

        do {
            $published = $this->relayBatch($publisher);
            if ($published > 0) {
                $this->line(now()->format('H:i:s')." Đã publish {$published} event.");
            }
            $consecutiveBrokerFailures = $this->brokerFailed ? $consecutiveBrokerFailures + 1 : 0;
            if (! $this->option('once')) {
                usleep(self::backoffMs($sleepMs, $consecutiveBrokerFailures) * 1000);
            }
        } while (! $this->option('once'));

        $publisher->close();

        return self::SUCCESS;
    }

    /** Thời gian nghỉ trước lượt kế tiếp: gấp đôi sau mỗi lượt broker lỗi, tối đa MAX_BACKOFF_MS. */
    public static function backoffMs(int $sleepMs, int $consecutiveFailures): int
    {
        return (int) min(self::MAX_BACKOFF_MS, $sleepMs * (2 ** min($consecutiveFailures, 10)));
    }

    /** Event FAILED được thử lại sau 2^retry_count giây (tối đa 60 giây) kể từ lần lỗi trước. */
    public static function isDue(OutboxEvent $event, Carbon $now): bool
    {
        if ($event->status === OutboxEvent::PENDING) {
            return true;
        }

        $waitSeconds = min(60, 2 ** min($event->retry_count, 6));

        return $event->updated_at === null || $event->updated_at->copy()->addSeconds($waitSeconds)->lte($now);
    }

    private function relayBatch(RabbitMqPublisher $publisher): int
    {
        $this->brokerFailed = false;
        $now = now();
        $events = OutboxEvent::query()
            ->whereIn('status', [OutboxEvent::PENDING, OutboxEvent::FAILED])
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->filter(fn (OutboxEvent $event): bool => self::isDue($event, $now));

        $published = 0;
        foreach ($events as $event) {
            try {
                $publisher->publish($event->routing_key, $event->payload, $event->event_id);
                $event->update(['status' => OutboxEvent::PUBLISHED, 'published_at' => now(), 'last_error' => null]);
                $published++;
            } catch (\Throwable $e) {
                $this->markFailed($event, $e);
                $publisher->close();

                if ($e instanceof UnroutableMessageException) {
                    // Lỗi cấu hình của riêng event này: ghi lại, gửi tiếp các event sau
                    continue;
                }

                // Broker lỗi: dừng lượt này để giữ thứ tự, lượt sau thử lại từ event lỗi
                $this->brokerFailed = true;
                break;
            }
        }

        return $published;
    }

    private function markFailed(OutboxEvent $event, \Throwable $e): void
    {
        $event->update([
            'status' => OutboxEvent::FAILED,
            'retry_count' => $event->retry_count + 1,
            'last_error' => mb_substr($e->getMessage(), 0, 1000),
        ]);
        $this->warn("Publish {$event->event_type} ({$event->event_id}) thất bại lần {$event->retry_count}: {$e->getMessage()}");
    }
}
