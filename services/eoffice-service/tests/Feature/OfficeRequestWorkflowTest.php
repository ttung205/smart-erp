<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*/api/users/42' => Http::response(['success' => true, 'data' => [
                'id' => 42, 'department_id' => 2, 'line_manager_id' => 8, 'line_manager_name' => 'Trưởng phòng',
            ]]),
            '*/api/users' => Http::response(['success' => true, 'data' => []]),
        ]);
        Storage::fake('local');
    }

    private function payload(string $kind): array
    {
        return [
            'category' => $kind === 'purchase' ? 'OFFICE_SUPPLIES' : 'HOSPITALITY',
            'purpose' => 'Phục vụ buổi họp khách hàng',
            'supplier_name' => 'Nhà cung cấp A',
            'items' => [['name' => 'Vật tư', 'unit' => 'Cái', 'quantity' => 2, 'unit_price' => 150000]],
        ];
    }

    public function test_purchase_request_is_approved_through_shared_inbox(): void
    {
        $employee = $this->jwtHeaders(42);
        $id = $this->withHeaders($employee)->postJson('/api/purchase-requests', $this->payload('purchase'))
            ->assertCreated()->assertJsonPath('data.total_amount', '300000.00')->json('data.id');

        $this->withHeaders($employee)->postJson("/api/purchase-requests/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'PENDING');

        $manager = $this->jwtHeaders(8);
        $step = $this->withHeaders($manager)->getJson('/api/approvals/inbox')
            ->assertOk()->assertJsonPath('data.0.document.code', 'MS-'.now()->year.'-0001')->json('data.0.id');
        $this->withHeaders($employee)->postJson("/api/approvals/{$step}/approve")->assertForbidden();
        $this->withHeaders($manager)->postJson("/api/approvals/{$step}/approve")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->withHeaders($employee)->getJson("/api/purchase-requests/{$id}")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_payment_requires_attachment_and_other_users_cannot_access_draft(): void
    {
        $employee = $this->jwtHeaders(42);
        $id = $this->withHeaders($employee)->postJson('/api/payment-requests', $this->payload('payment'))
            ->assertCreated()->json('data.id');

        $this->withHeaders($this->jwtHeaders(8))->getJson("/api/payment-requests/{$id}")->assertForbidden();
        $this->withHeaders($employee)->postJson("/api/payment-requests/{$id}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $file = UploadedFile::fake()->create('hoa-don.pdf', 50, 'application/pdf');
        $attachment = $this->withHeaders($employee)->post("/api/payment-requests/{$id}/attachments", ['file' => $file])
            ->assertCreated()->json('data');
        $this->withHeaders($employee)->postJson("/api/payment-requests/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->withHeaders($this->jwtHeaders(8))->get("/api/payment-requests/{$id}/attachments/{$attachment['id']}")->assertOk();
        $this->withHeaders($employee)->deleteJson("/api/payment-requests/{$id}/attachments/{$attachment['id']}")->assertForbidden();
    }

    public function test_kind_and_category_cannot_be_mixed(): void
    {
        $employee = $this->jwtHeaders(42);
        $this->withHeaders($employee)->postJson('/api/payment-requests', $this->payload('purchase'))
            ->assertUnprocessable()->assertJsonValidationErrors('category');
        $id = $this->withHeaders($employee)->postJson('/api/purchase-requests', $this->payload('purchase'))
            ->assertCreated()->json('data.id');
        $this->withHeaders($employee)->getJson("/api/payment-requests/{$id}")->assertNotFound();
    }
}
