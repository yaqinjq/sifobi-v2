<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Services\Ocr\DocumentOcrService;
use App\Services\Ocr\OcrEngineUnavailableException;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet->id,
        'status' => 'ACTIVE',
    ]);
    $this->user->assignRole('STAFF_GUDANG');
});

test('scan-photo mengembalikan baris saran dari DocumentOcrService', function (): void {
    $fake = Mockery::mock(DocumentOcrService::class);
    $fake->shouldReceive('suggestRows')->once()->andReturn([
        ['item_id' => null, 'match_score' => 0.0, 'raw_name' => 'Smoked Chicken', 'qty' => 10.0, 'unit_text' => 'pack', 'confidence' => 0.6],
    ]);
    $this->app->instance(DocumentOcrService::class, $fake);

    $response = $this->actingAs($this->user)->postJson(route('api.goods-receipts.scan-photo'), [
        'photo' => UploadedFile::fake()->image('surat-jalan.jpg'),
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('rows.0.raw_name', 'Smoked Chicken');
});

test('scan-photo balas 422 (bukan 500) saat engine OCR gagal', function (): void {
    $fake = Mockery::mock(DocumentOcrService::class);
    $fake->shouldReceive('suggestRows')->once()->andThrow(new OcrEngineUnavailableException('OCR tidak tersedia.'));
    $this->app->instance(DocumentOcrService::class, $fake);

    $response = $this->actingAs($this->user)->postJson(route('api.goods-receipts.scan-photo'), [
        'photo' => UploadedFile::fake()->image('surat-jalan.jpg'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'OCR tidak tersedia.');
});

test('guest tidak bisa akses scan-photo', function (): void {
    $this->postJson(route('api.goods-receipts.scan-photo'), [
        'photo' => UploadedFile::fake()->image('surat-jalan.jpg'),
    ])->assertStatus(401);
});

test('scan-photo menolak file yang bukan gambar', function (): void {
    $response = $this->actingAs($this->user)->postJson(route('api.goods-receipts.scan-photo'), [
        'photo' => UploadedFile::fake()->create('dokumen.pdf', 100),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('photo');
});
