<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMqPublisher;
use App\Models\OutboxEvent;
use Illuminate\Console\Command;

/** Quét outbox_events và đẩy lên RabbitMQ (at-least-once; consumer tự loại trùng theo event_id). */
class RelayOutboxEvents extends Command
{
    protected $signature = 'outbox:relay {--once : Chỉ quét một lượt rồi thoát} {--sleep=1500 : Thời gian nghỉ giữa các lượt (ms)}';

    protected $description = 'Publish các domain event đang chờ trong outbox_events lên RabbitMQ';

    public function handle(RabbitMqPublisher $publisher): int
    {
        // Mỗi service chỉ chạy 1 relay để giữ thứ tự publish
        $lock = fopen(storage_path('framework/outbox-relay.lock'), 'c');
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Outbox relay đã chạy ở tiến trình khác, bỏ qua.');

            return self::SUCCESS;
        }

        $sleepMs = max(200, (int) $this->option('sleep'));
        $this->info('Outbox relay đã khởi động.');

        do {
            $published = $this->relayBatch($publisher);
            if ($published > 0) {
                $this->line(now()->format('H:i:s')." Đã publish {$published} event.");
            }
            if (! $this->option('once')) {
                usleep($sleepMs * 1000);
            }
        } while (! $this->option('once'));

        $publisher->close();

        return self::SUCCESS;
    }

    private function relayBatch(RabbitMqPublisher $publisher): int
    {
        $events = OutboxEvent::query()
            ->where(fn ($query) => $query->where('status', OutboxEvent::PENDING)
                ->orWhere(fn ($failed) => $failed->where('status', OutboxEvent::FAILED)->where('retry_count', '<', OutboxEvent::MAX_RETRIES)))
            ->orderBy('id')
            ->limit(50)
            ->get();

        $published = 0;
        foreach ($events as $event) {
            try {
                $publisher->publish($event->routing_key, $event->payload, $event->event_id);
                $event->update(['status' => OutboxEvent::PUBLISHED, 'published_at' => now(), 'last_error' => null]);
                $published++;
            } catch (\Throwable $e) {
                $event->update([
                    'status' => OutboxEvent::FAILED,
                    'retry_count' => $event->retry_count + 1,
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
                $publisher->close();
                $this->warn("Publish {$event->event_type} ({$event->event_id}) thất bại: {$e->getMessage()}");

                // Giữ thứ tự: dừng lượt này, lượt sau thử lại từ event lỗi
                break;
            }
        }

        return $published;
    }
}
