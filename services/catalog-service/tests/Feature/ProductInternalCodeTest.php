<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Internal Code (cột sku) do server sinh theo {DESIGNATION}-{3 chữ đầu brand}, không nhập tay. */
class ProductInternalCodeTest extends TestCase
{
    use RefreshDatabase;

    private function brandId(string $name): int
    {
        return DB::table('brands')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function create(array $body): TestResponse
    {
        return $this->withHeaders($this->jwtHeaders(['catalog.create']))->postJson('/api/products', $body);
    }

    public function test_generates_code_from_designation_and_first_three_letters_of_brand(): void
    {
        $this->create(['designation' => 'XQ530', 'brand_id' => $this->brandId('Samsung')])
            ->assertCreated()->assertJsonPath('data.sku', 'XQ530-SAM');

        $this->assertDatabaseHas('products', ['sku' => 'XQ530-SAM', 'designation' => 'XQ530']);
    }

    public function test_ignores_a_code_typed_by_the_client(): void
    {
        $this->create(['sku' => 'TU-NHAP', 'designation' => 'XQ530', 'brand_id' => $this->brandId('LG')])
            ->assertCreated()->assertJsonPath('data.sku', 'XQ530-LG');

        $this->assertDatabaseMissing('products', ['sku' => 'TU-NHAP']);
    }

    public function test_strips_vietnamese_accents_and_special_characters(): void
    {
        $this->create(['designation' => 'Tủ lạnh 350L', 'brand_id' => $this->brandId('Đại Việt')])
            ->assertCreated()->assertJsonPath('data.sku', 'TU-LANH-350L-DAI');
    }

    public function test_appends_a_numeric_suffix_when_the_code_already_exists(): void
    {
        $brand = $this->brandId('Daikin');

        $this->create(['designation' => 'FTKF25', 'brand_id' => $brand])->assertJsonPath('data.sku', 'FTKF25-DAI');
        $this->create(['designation' => 'FTKF25', 'brand_id' => $brand])->assertJsonPath('data.sku', 'FTKF25-DAI-2');
        $this->create(['designation' => 'FTKF25', 'brand_id' => $brand])->assertJsonPath('data.sku', 'FTKF25-DAI-3');
    }

    public function test_creates_the_brand_by_name_when_only_the_name_is_sent(): void
    {
        $this->create(['designation' => 'A1', 'brand' => 'Toshiba'])
            ->assertCreated()->assertJsonPath('data.sku', 'A1-TOS');

        $this->assertDatabaseHas('brands', ['name' => 'Toshiba']);
    }

    public function test_requires_designation_and_brand(): void
    {
        $this->create(['designation' => 'A1'])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->create(['brand_id' => $this->brandId('LG')])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertDatabaseCount('products', 0);
    }
}
