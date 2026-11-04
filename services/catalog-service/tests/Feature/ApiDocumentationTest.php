<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_swagger_ui_and_spec_are_hidden_outside_local_environment(): void
    {
        $this->get('/swagger')->assertForbidden();
        $this->get('/docs')->assertForbidden();
    }

    public function test_swagger_ui_is_served_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->get('/swagger')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }

    public function test_generated_spec_documents_each_authentication_mode(): void
    {
        $this->app['env'] = 'local';
        Artisan::call('l5-swagger:generate');

        $document = $this->getJson('/docs')
            ->assertOk()
            ->assertJsonPath('info.title', 'Smart ERP - Catalog Service API')
            ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
            ->assertJsonPath('components.securitySchemes.internalServiceKey.name', 'X-Internal-Service-Key')
            ->json();

        $this->assertSame([], $document['paths']['/health']['get']['security']);
        $this->assertSame([['bearerAuth' => []]], $document['paths']['/brands']['get']['security']);
        // Đọc sản phẩm: JWT hoặc khóa nội bộ
        $this->assertSame(
            [['bearerAuth' => []], ['internalServiceKey' => []]],
            $document['paths']['/products']['get']['security'],
        );
        // Áp kết quả hỏi hàng: cần cả JWT và khóa nội bộ
        $this->assertSame(
            [['bearerAuth' => [], 'internalServiceKey' => []]],
            $document['paths']['/products/apply-inquiry-result']['post']['security'],
        );
    }
}
