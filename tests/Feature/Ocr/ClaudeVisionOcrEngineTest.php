<?php

use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\Tenant;
use App\Services\Ocr\ClaudeVisionOcrEngine;
use App\Services\Ocr\OcrEngineUnavailableException;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->profile = IntegrationProfile::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'OCR_CLAUDE',
        'provider' => 'OCR_CLAUDE',
        'name' => 'Claude Vision OCR',
        'base_url' => 'https://api.anthropic.com',
        'auth_token' => 'sk-test-key',
        'is_active' => true,
    ]);

    $this->imagePath = tempnam(sys_get_temp_dir(), 'ocr-test-');
    // 1x1 px PNG, cukup utk getimagesize() mendeteksi mime tanpa perlu foto asli.
    file_put_contents($this->imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
});

afterEach(function (): void {
    if (isset($this->imagePath) && file_exists($this->imagePath)) {
        unlink($this->imagePath);
    }
});

test('extractRows memetakan respons JSON Anthropic ke baris item', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    ['name' => 'Smoked Chicken', 'qty' => 10, 'unit' => 'pack'],
                    ['name' => 'Onde-Onde', 'qty' => 5, 'unit' => 'pack'],
                ]),
            ]],
        ], 200),
    ]);

    $engine = new ClaudeVisionOcrEngine($this->profile);
    $rows = $engine->extractRows($this->imagePath);

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBe(['raw_name' => 'Smoked Chicken', 'qty' => 10.0, 'unit_text' => 'pack', 'confidence' => 0.85]);
    expect($rows[1]['raw_name'])->toBe('Onde-Onde');

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'sk-test-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request->url() === 'https://api.anthropic.com/v1/messages';
    });
});

test('extractRows membersihkan code fence markdown dari respons', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => "```json\n".json_encode([['name' => 'Ajinomoto', 'qty' => null, 'unit' => null]])."\n```",
            ]],
        ], 200),
    ]);

    $engine = new ClaudeVisionOcrEngine($this->profile);
    $rows = $engine->extractRows($this->imagePath);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['raw_name'])->toBe('Ajinomoto');
    expect($rows[0]['qty'])->toBeNull();
});

test('extractRows melempar OcrEngineUnavailableException saat API merespons error', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401),
    ]);

    $engine = new ClaudeVisionOcrEngine($this->profile);

    expect(fn () => $engine->extractRows($this->imagePath))
        ->toThrow(OcrEngineUnavailableException::class);
});

test('extractRows melempar OcrEngineUnavailableException kalau respons bukan JSON valid', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'maaf, saya tidak bisa membaca dokumen ini.']],
        ], 200),
    ]);

    $engine = new ClaudeVisionOcrEngine($this->profile);

    expect(fn () => $engine->extractRows($this->imagePath))
        ->toThrow(OcrEngineUnavailableException::class);
});
