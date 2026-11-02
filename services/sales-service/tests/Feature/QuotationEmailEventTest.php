<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use App\Models\Quotation;
use App\Models\SalesAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gửi email báo giá đi theo event: Sales chỉ ghi QuotationEmailRequested vào outbox,
 * notification-service nhận event và gửi mail. Sales không gọi SMTP hay notification-service.
 */
class QuotationEmailEventTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(string $number = 'QT-MAIL-001'): Quotation
    {
        return Quotation::create([
            'quotation_number' => $number, 'customer_name' => 'Khách A', 'quotation_date' => '2026-10-15',
            'status' => 'APPROVED', 'currency' => 'VND', 'exchange_rate' => 1, 'total_amount' => 1100,
            'created_by' => 'Người tạo', 'created_by_id' => 7,
        ]);
    }

    private function attachment(Quotation $quotation, string $type = 'quotation'): SalesAttachment
    {
        return SalesAttachment::create([
            'attachable_type' => $type, 'attachable_id' => $quotation->id, 'file_name' => 'QT-MAIL-001.pdf',
            'file_path' => 'attachments/quotation/x.pdf', 'file_size' => 100, 'mime_type' => 'application/pdf', 'uploaded_by' => 'Test User',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return [
            'to' => ['khach@example.com'], 'cc' => ['sep@example.com'],
            'subject' => 'Báo giá QT-MAIL-001', 'body' => "Kính gửi anh/chị,\nXin gửi báo giá.", 'sender_name' => 'Công ty ABC',
            ...$override,
        ];
    }

    public function test_records_email_requested_event_with_content_and_attachment_reference(): void
    {
        $quotation = $this->quotation();
        $attachment = $this->attachment($quotation);

        $response = $this->withHeaders($this->jwtHeaders(['quotation.update']))
            ->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['attachment_ids' => [$attachment->id]]))
            ->assertStatus(202)->assertJsonPath('data.status', 'REQUESTED');

        $event = OutboxEvent::sole();
        $this->assertSame('sales.quotation.email_requested', $event->routing_key);
        $envelope = json_decode($event->payload, true);
        $this->assertSame('QuotationEmailRequested', $envelope['event_type']);
        $this->assertSame($response->json('data.email_job_id'), $envelope['payload']['email']['job_id']);
        $this->assertSame(['khach@example.com'], $envelope['payload']['email']['to']);
        $this->assertSame(['sep@example.com'], $envelope['payload']['email']['cc']);
        $this->assertSame('Báo giá QT-MAIL-001', $envelope['payload']['email']['subject']);
        $this->assertSame(
            [['source' => 'sales', 'id' => $attachment->id, 'filename' => 'QT-MAIL-001.pdf', 'content_type' => 'application/pdf']],
            $envelope['payload']['attachments'],
        );
        // Event không chứa nội dung tệp
        $this->assertStringNotContainsString('base64', $event->payload);
    }

    public function test_rejects_attachment_of_another_document(): void
    {
        $quotation = $this->quotation();
        $other = $this->attachment($this->quotation('QT-MAIL-002'), 'invoice');

        $this->withHeaders($this->jwtHeaders(['quotation.update']))
            ->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['attachment_ids' => [$other->id]]))
            ->assertStatus(422)->assertJsonPath('error.code', 'ATTACHMENT_NOT_FOUND');

        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_validates_recipients_and_subject_and_strips_line_breaks(): void
    {
        $quotation = $this->quotation();
        $headers = $this->jwtHeaders(['quotation.update']);

        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['to' => ['không-phải-email']]))
            ->assertStatus(422)->assertJsonValidationErrors('to.0');
        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['to' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('to');
        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['subject' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('subject');

        $many = array_map(fn (int $i): string => "u{$i}@example.com", range(1, 30));
        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['to' => $many, 'cc' => $many]))
            ->assertStatus(422)->assertJsonPath('error.code', 'TOO_MANY_RECIPIENTS');

        $this->withHeaders($headers)->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload(['subject' => "Báo giá\r\nBcc: x@evil.test"]))
            ->assertStatus(202);
        $this->assertSame('Báo giá Bcc: x@evil.test', json_decode(OutboxEvent::sole()->payload, true)['payload']['email']['subject']);
    }

    public function test_requires_permission_and_existing_quotation(): void
    {
        $quotation = $this->quotation();

        $this->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload())->assertUnauthorized();
        $this->withHeaders($this->jwtHeaders(['quotation.view']))
            ->postJson("/api/quotations/{$quotation->id}/send-email", $this->payload())->assertForbidden();
        $this->withHeaders($this->jwtHeaders(['quotation.update']))
            ->postJson('/api/quotations/999999/send-email', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_internal_attachment_download_requires_the_service_key(): void
    {
        config(['services.internal.key' => 'internal-test-key']);

        $this->getJson('/api/internal/attachments/1/download')->assertForbidden();
        $this->getJson('/api/internal/attachments/1/download', ['X-Internal-Service-Key' => 'wrong'])->assertForbidden();
        // Có khóa đúng thì qua được lớp xác thực (tệp không tồn tại nên 404)
        $this->getJson('/api/internal/attachments/1/download', ['X-Internal-Service-Key' => 'internal-test-key'])->assertNotFound();
    }
}
