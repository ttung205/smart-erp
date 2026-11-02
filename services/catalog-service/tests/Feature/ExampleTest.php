<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Service chỉ phục vụ API nên không có trang chủ; kiểm tra endpoint health thay cho route `/` mặc định của Laravel.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()->assertJsonPath('data.service', 'catalog-service');
    }
}
