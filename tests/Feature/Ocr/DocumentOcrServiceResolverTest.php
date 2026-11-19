<?php

use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\Tenant;
use App\Services\Ocr\ClaudeVisionOcrEngine;
use App\Services\Ocr\DocumentOcrService;
use App\Services\Ocr\TesseractOcrEngine;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
});

test('engineFor memilih TesseractOcrEngine kalau belum ada profil OCR_CLAUDE aktif', function (): void {
    $service = app(DocumentOcrService::class);

    expect($service->engineFor($this->tenant->id))->toBeInstanceOf(TesseractOcrEngine::class);
});

test('engineFor memilih TesseractOcrEngine kalau profil OCR_CLAUDE ada tapi nonaktif', function (): void {
    IntegrationProfile::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'OCR_CLAUDE',
        'provider' => 'OCR_CLAUDE',
        'name' => 'Claude Vision OCR',
        'base_url' => 'https://api.anthropic.com',
        'auth_token' => 'sk-test',
        'is_active' => false,
    ]);

    $service = app(DocumentOcrService::class);

    expect($service->engineFor($this->tenant->id))->toBeInstanceOf(TesseractOcrEngine::class);
});

test('engineFor memilih ClaudeVisionOcrEngine kalau profil OCR_CLAUDE aktif', function (): void {
    IntegrationProfile::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'OCR_CLAUDE',
        'provider' => 'OCR_CLAUDE',
        'name' => 'Claude Vision OCR',
        'base_url' => 'https://api.anthropic.com',
        'auth_token' => 'sk-test',
        'is_active' => true,
    ]);

    $service = app(DocumentOcrService::class);

    expect($service->engineFor($this->tenant->id))->toBeInstanceOf(ClaudeVisionOcrEngine::class);
});

test('engineFor tidak terpengaruh profil OCR_CLAUDE aktif milik tenant lain', function (): void {
    $otherTenant = Tenant::query()->create([
        'code' => 'OTHERTEN',
        'name' => 'Other Tenant',
        'status' => 'ACTIVE',
    ]);

    IntegrationProfile::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'code' => 'OCR_CLAUDE',
        'provider' => 'OCR_CLAUDE',
        'name' => 'Claude Vision OCR',
        'base_url' => 'https://api.anthropic.com',
        'auth_token' => 'sk-test',
        'is_active' => true,
    ]);

    $service = app(DocumentOcrService::class);

    expect($service->engineFor($this->tenant->id))->toBeInstanceOf(TesseractOcrEngine::class);
});
