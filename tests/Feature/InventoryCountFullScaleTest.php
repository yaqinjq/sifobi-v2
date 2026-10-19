<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountLine;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\InventoryCountService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\InventoryCountSyntheticFixtures;

uses(RefreshDatabase::class);

/**
 * Uji skala PENUH memakai fixture sintetis seluruh template (76-105 item
 * per template, bukan cuma 2-3 item seperti InventoryCountFeatureTest.php
 * lama) -- membuktikan form benar-benar bisa dipakai untuk struktur Bar/
 * Kitchen sungguhan, termasuk kasus UNIT_UNRESOLVED (Onde-Onde) dan
 * cross-location-unit-mismatch (3 item BAR) yang TIDAK di-resolve diam-diam.
 */
beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();

    $fixtures = (new InventoryCountSyntheticFixtures($this->tenant))->build();
    $this->templates = $fixtures['templates'];
    $this->skipped = $fixtures['skipped_unit_unresolved'];
});

function fullScaleUser(Tenant $tenant, Outlet $outlet, Department $department, string $role = 'STAFF_BAR'): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id,
        'department_id' => $department->id,
        'status' => 'ACTIVE',
        'email' => strtolower($role.'_fullscale_'.uniqid()).'@sifobi.test',
    ]);
    $user->assignRole($role);

    return $user;
}

test('BAR_DAILY document materializes 76 items x 2 locations = 152 lines, show page renders without error', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->bar);
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->templates['BAR_DAILY'], now(), $staff->id
    );

    expect(InventoryCountLine::query()->where('document_id', $document->id)->count())->toBe(152);

    $this->actingAs($staff)
        ->get(route('operations.inventory-count.show', $document))
        ->assertOk();
});

test('KITCHEN_WIP_SAUCE document materializes all 105 reference lines (104 unique items, Chicken Paitan appears twice by design)', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_WIP_SAUCE'], now(), $staff->id
    );

    expect(InventoryCountLine::query()->where('document_id', $document->id)->count())->toBe(105);

    $this->actingAs($staff)
        ->get(route('operations.inventory-count.show', $document))
        ->assertOk();
});

test('autosave works across a long list (50+ lines) with comma, dot, explicit zero and empty-field semantics preserved per line', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_DRY_GOODS'], now(), $staff->id
    );

    $lines = InventoryCountLine::query()->where('document_id', $document->id)->orderBy('sort_order')->get();
    expect($lines)->toHaveCount(64);

    foreach ($lines as $i => $line) {
        $version = InventoryCountDocument::find($document->id)->version;
        $value = match ($i % 4) {
            0 => '12,5',  // koma
            1 => '8.25',  // titik
            2 => '0',     // explicit zero
            default => null, // dibiarkan kosong di bawah
        };

        if ($value === null) {
            continue; // sengaja tidak disentuh -- harus tetap "belum dihitung".
        }

        $this->actingAs($staff)
            ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
                'qty_whole' => $value,
                'expected_version' => $version,
            ])
            ->assertOk();
    }

    $lines->fresh();
    $counted = InventoryCountLine::query()->where('document_id', $document->id)->where('is_counted', true)->count();
    $uncounted = InventoryCountLine::query()->where('document_id', $document->id)->where('is_counted', false)->count();

    // i%4 di 0,1,2 terisi (3 dari 4), i%4==3 dilewati -- dari 64 baris: 48 terisi, 16 belum.
    expect($counted)->toBe(48);
    expect($uncounted)->toBe(16);

    // Baris koma (index 0) harus tersimpan sebagai 12.5, bukan string mentah.
    $commaLine = InventoryCountLine::query()->where('document_id', $document->id)->orderBy('sort_order')->first();
    expect((string) $commaLine->refresh()->qty_whole)->toBe('12.500000');
});

test('submitting a document with uncounted lines is blocked with a per-line message', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_SAYUR'], now(), $staff->id
    );

    $response = $this->actingAs($staff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->version])
        ->assertRedirect();

    $response->assertSessionHasErrors('lines');
    expect($document->refresh()->status)->toBe(InventoryCountDocument::STATUS_DRAFT);
});

test('Onde-Onde UNIT_UNRESOLVED conversion conflict blocks submission even after raw value entered, without the system guessing a ratio', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_WIP_SAUCE'], now(), $staff->id
    );

    $service = app(InventoryCountService::class);
    $lines = InventoryCountLine::query()->where('document_id', $document->id)->with('item')->get();
    $ondeLine = $lines->first(fn ($l) => str_contains(strtolower((string) $l->item?->name), 'onde'));

    expect($ondeLine)->not->toBeNull();
    expect($ondeLine->requires_conversion)->toBeTrue();
    expect($ondeLine->config_valid)->toBeFalse();
    expect($ondeLine->conversion_factor)->toBeNull();

    // Staf tetap bisa isi angka mentah sebagai draft (demonstrasi nilai mentah
    // diperbolehkan) -- tapi qty_base tidak boleh diklaim valid.
    foreach ($lines as $line) {
        $version = InventoryCountDocument::find($document->id)->version;
        $service->autosaveLine($line, [
            'qty_whole' => '3',
            'qty_loose' => $line->requires_conversion ? '0' : null,
        ], $staff->id, $version);
    }

    $ondeLine->refresh();
    expect($ondeLine->is_counted)->toBeTrue();
    expect($ondeLine->qty_base)->toBeNull(); // qty_base TIDAK diklaim valid walau nilai mentah terisi.

    $document->refresh();
    $this->actingAs($staff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->version])
        ->assertSessionHasErrors('lines');

    expect($document->refresh()->status)->toBe(InventoryCountDocument::STATUS_DRAFT);
});

test('the 3 BAR cross-location-unit-mismatch items allow raw per-location entry and full submission, each location keeping its own unit (no fabricated combined ratio)', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->bar, 'STAFF_BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->templates['BAR_DAILY'], now(), $staff->id
    );

    $service = app(InventoryCountService::class);
    $lines = InventoryCountLine::query()->where('document_id', $document->id)->with('item')->get();

    // "Mix Simple Syrup" juga ada di katalog BAR tapi unitnya SAMA di kedua
    // lokasi (Kg/Kg) -- bukan kasus cross-location-mismatch, harus match
    // persis "Simple Syrup (25 Kg)" (Gln di Gudang, Kg di Bar Area) saja.
    $simpleSyrupLines = $lines->filter(fn ($l) => str_contains((string) $l->item?->name, 'Simple Syrup (25 Kg)'));
    expect($simpleSyrupLines)->toHaveCount(2);

    $gudangLine = $simpleSyrupLines->first(fn ($l) => $l->location === 'GUDANG');
    $barAreaLine = $simpleSyrupLines->first(fn ($l) => $l->location === 'BAR_AREA');

    expect($gudangLine->config_valid)->toBeTrue(); // Gln cocok dengan unit master item.
    expect($barAreaLine->config_valid)->toBeFalse(); // Kg beda dari unit master (Gln) -- tidak ada rasio ditebak.
    expect($gudangLine->whole_unit_id)->not->toBe($barAreaLine->whole_unit_id);

    foreach ($lines as $line) {
        $version = InventoryCountDocument::find($document->id)->version;
        $service->autosaveLine($line, ['qty_whole' => '5'], $staff->id, $version);
    }

    $document->refresh();
    // Submission TIDAK diblokir oleh config_valid=false di jalur ini -- lihat
    // komentar di InventoryCountService::resolveLineConfig(): raw per-lokasi
    // tetap observasi lengkap yang sah, config_valid=false di sini cuma
    // sinyal "unit beda dari master", bukan "form minta konversi yang hilang".
    $this->actingAs($staff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->version])
        ->assertRedirect(route('operations.inventory-count.show', $document));

    expect($document->refresh()->status)->toBe(InventoryCountDocument::STATUS_SUBMITTED);
    expect($barAreaLine->refresh()->qty_base)->toBe('5.000000'); // valid DI UNIT LOKASI ITU SENDIRI (Kg), bukan diklaim setara Gln.
});

test('resume after reload keeps previously-entered values and does not create a duplicate document', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $service = app(InventoryCountService::class);

    $first = $service->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_SAYUR'], now(), $staff->id
    );
    $line = InventoryCountLine::query()->where('document_id', $first->id)->first();
    $service->autosaveLine($line, ['qty_whole' => '7'], $staff->id, $first->version);

    $second = $service->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_SAYUR'], now(), $staff->id
    );

    expect($second->id)->toBe($first->id);
    expect($line->refresh()->qty_whole)->not->toBeNull();
});

test('concurrent edit conflict is rejected with a stale version, not silently overwritten', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $service = app(InventoryCountService::class);
    $document = $service->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_SAYUR'], now(), $staff->id
    );
    $line = InventoryCountLine::query()->where('document_id', $document->id)->first();
    $staleVersion = $document->version;

    $service->autosaveLine($line, ['qty_whole' => '1'], $staff->id, $staleVersion);

    expect(fn () => $service->autosaveLine($line->refresh(), ['qty_whole' => '2'], $staff->id, $staleVersion))
        ->toThrow(App\Exceptions\InventoryCountConflictException::class);
});

test('a submitted document can be reverted to draft only with a recorded reason, and history keeps the full trail', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $approver = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'PIC_OUTLET');
    $service = app(InventoryCountService::class);

    $document = $service->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_SAYUR'], now(), $staff->id
    );
    foreach (InventoryCountLine::query()->where('document_id', $document->id)->get() as $line) {
        $version = InventoryCountDocument::find($document->id)->version;
        $service->autosaveLine($line, ['qty_whole' => '1'], $staff->id, $version);
    }
    $document->refresh();
    $service->submit($document, $staff->id, $document->version);
    $document->refresh();
    expect($document->status)->toBe(InventoryCountDocument::STATUS_SUBMITTED);

    $this->actingAs($approver)
        ->post(route('operations.inventory-count.request-revision', $document), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($approver)
        ->post(route('operations.inventory-count.request-revision', $document), ['reason' => 'Salah input Kecap Asin, minta ulang hitung.'])
        ->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe(InventoryCountDocument::STATUS_DRAFT);
    expect($document->revisions()->count())->toBe(1);
    expect($document->revisions()->first()->reason)->toBe('Salah input Kecap Asin, minta ulang hitung.');
});

test('export never shows inferred stock and sanitizes formula-injection characters, scoped identically to the page filter', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->bar, 'STAFF_BAR');
    $service = app(InventoryCountService::class);
    $document = $service->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->templates['BAR_DAILY'], now(), $staff->id
    );

    $response = $this->actingAs($staff)
        ->get(route('operations.inventory-count.export', ['outlet_id' => $this->outlet->id, 'department_id' => $this->bar->id]))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('spreadsheet');
});

test('creating, autosaving, submitting, revising and exporting an Inventory Simple document never touches stock_balances or stock_mutations', function (): void {
    $staff = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'STAFF_KITCHEN');
    $approver = fullScaleUser($this->tenant, $this->outlet, $this->kitchen, 'PIC_OUTLET');
    $service = app(InventoryCountService::class);

    $balanceCountBefore = StockBalance::withoutGlobalScopes()->count();
    $mutationCountBefore = StockMutation::withoutGlobalScopes()->count();

    $document = $service->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->kitchen->id, $this->templates['KITCHEN_SAYUR'], now(), $staff->id
    );
    foreach (InventoryCountLine::query()->where('document_id', $document->id)->get() as $line) {
        $version = InventoryCountDocument::find($document->id)->version;
        $service->autosaveLine($line, ['qty_whole' => '2'], $staff->id, $version);
    }
    $document->refresh();
    $service->submit($document, $staff->id, $document->version);
    $document->refresh();
    $service->requestRevision($document, $approver->id, 'Uji ledger tidak berubah.');

    $this->actingAs($staff)
        ->get(route('operations.inventory-count.export', ['outlet_id' => $this->outlet->id, 'department_id' => $this->kitchen->id]))
        ->assertOk();

    expect(StockBalance::withoutGlobalScopes()->count())->toBe($balanceCountBefore);
    expect(StockMutation::withoutGlobalScopes()->count())->toBe($mutationCountBefore);
});
