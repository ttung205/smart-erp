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

    public function test_generated_spec_marks_public_routes_and_requires_bearer_on_protected_routes(): void
    {
        $this->app['env'] = 'local';
        Artisan::call('l5-swagger:generate');

        $document = $this->getJson('/docs')
            ->assertOk()
            ->assertJsonPath('info.title', 'Smart ERP - Finance Service API')
            ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
            ->json();

        $this->assertSame([], $document['paths']['/health']['get']['security']);
        $this->assertSame([['bearerAuth' => []]], $document['paths']['/vouchers']['get']['security']);
        $this->assertSame([['bearerAuth' => []]], $document['paths']['/vouchers/{id}/post']['post']['security']);
    }
}
