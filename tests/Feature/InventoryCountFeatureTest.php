<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountLine;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMutation;
use App\Services\InventoryCountService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Kebijakan yang diuji: modul Inventory Simple murni pendataan fisik --
 * TIDAK PERNAH memanggil service posting existing atau mengubah
 * stock_balances/stock_mutations (lihat test "does not touch ledger..." di
 * bawah). Scope tenant/outlet/departemen mandiri (EnforcesInventorySimpleScope),
 * TIDAK bergantung pada branch fix/spoil-waste-department-scope.
 */
beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->otherOutlet = Outlet::query()->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->pastry = Department::query()->where('code', 'PASTRY')->firstOrFail();

    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->pastryItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();

    $this->barTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'department_id' => $this->bar->id,
        'code' => 'BAR_DAILY',
        'name' => 'Bar Harian',
        'frequency' => 'DAILY',
        'locations' => ['GUDANG', 'BAR_AREA'],
        'is_generic' => false,
        'is_configured' => true,
    ]);

    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'template_id' => $this->barTemplate->id,
        'item_id' => $this->barItem->id,
        'subcategory_label' => 'BUMBU',
        'sort_order' => 1,
        'locations' => ['GUDANG', 'BAR_AREA'],
        'requires_conversion' => false,
        'is_active' => true,
    ]);

    $this->pastryTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'department_id' => $this->pastry->id,
        'code' => 'PASTRY_DAILY',
        'name' => 'Pastry Harian',
        'frequency' => 'DAILY',
        'locations' => ['GENERIC'],
        'is_generic' => false,
        'is_configured' => false, // belum terverifikasi -- lihat test khusus.
    ]);

    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'template_id' => $this->pastryTemplate->id,
        'item_id' => $this->pastryItem->id,
        'subcategory_label' => 'BAKING',
        'sort_order' => 1,
        'locations' => ['GENERIC'],
        'requires_conversion' => false,
        'is_active' => true,
    ]);
});

function invUser(string $roleName, ?string $departmentCode, ?Outlet $outlet = null): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet ??= Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $department = $departmentCode ? Department::query()->where('code', $departmentCode)->firstOrFail() : null;

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $roleName === 'SUPER_ADMIN' ? null : $outlet->id,
        'department_id' => $department?->id,
        'status' => 'ACTIVE',
        'email' => strtolower($roleName.'_inv_'.($departmentCode ?? 'nodept').'_'.uniqid()).'@sifobi.test',
    ]);

    $user->assignRole($roleName);

    return $user;
}

function foreignTenantOutlet(): Outlet
{
    $suffix = uniqid();
    $tenant = Tenant::query()->create(['code' => 'OTHERTEN_'.$suffix, 'name' => 'Other Tenant', 'status' => 'ACTIVE']);
    $brand = Brand::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'code' => 'OTB_'.$suffix, 'name' => 'Other Brand', 'status' => 'ACTIVE']);
    $legal = LegalEntity::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'code' => 'OTLE_'.$suffix, 'name' => 'Other Legal', 'status' => 'ACTIVE']);

    return Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'brand_id' => $brand->id, 'legal_entity_id' => $legal->id,
        'code' => 'OTO_'.$suffix, 'name' => 'Foreign Outlet', 'status' => 'ACTIVE',
    ]);
}

// ===================== scope dasar =====================

test('the show page renders distinct List and Kartu mode bindings, not identical markup', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    $html = $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.show', $document))
        ->assertOk()
        ->getContent();

    // Mode Kartu memaksa field bertumpuk 1 kolom + input lebih besar +
    // subkategori diulang per kartu -- kalau binding ini tidak ada,
    // Kartu hanya beda warna tombol dari List (regresi UX yang pernah
    // ditemukan).
    //
    // PERINGATAN DARI PENGALAMAN NYATA: assertion string di bawah ini
    // CUMA membuktikan teks binding ADA di HTML -- bukan bahwa Alpine
    // benar-benar bisa MENGEVALUASINYA di browser. Versi sebelumnya
    // memakai "$parent.mode" di root elemen yang juga mendeklarasikan
    // x-data sendiri (line-card.blade.php) -- test string ini LULUS,
    // tapi di browser sungguhan Alpine melempar
    // "$parent is not defined" untuk SETIAP baris (terbukti lewat
    // console.error Playwright), sehingga Mode Kartu scara VISUAL
    // identik dengan List walau test ini hijau. Diperbaiki dengan
    // pindah ke $store.invCount.mode (global, tidak butuh $parent).
    // Lihat docs/technical/inventory-simple-fullscale-test-2026-10-09.md.
    expect($html)->toContain("\$store.invCount.mode === 'card' ? 'grid-cols-1'")
        ->and($html)->toContain("\$store.invCount.mode === 'card' ? 'text-xl min-h-14 text-center'")
        ->and($html)->toContain('$store.invCount.mode === \'card\'" x-cloak')
        ->and($html)->not->toContain('$parent.mode');
});

test('the show page renders a Mode Tabel toggle, a search box, and a table row per line with no $parent usage', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    $html = $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.show', $document))
        ->assertOk()
        ->getContent();

    expect($html)->toContain("\$store.invCount.mode = 'table'")
        ->and($html)->toContain('x-model="$store.invCount.search"')
        ->and($html)->toContain('matchesSearch()')
        // Cek pola BINDING yang rusak ("$parent.mode" dipakai sebagai
        // expression Alpine), BUKAN substring "$parent" mentah -- komentar
        // penjelasan bug lama di bawah ini sengaja menyebut kata itu.
        ->and($html)->not->toContain('$parent.mode')
        // barTemplate di beforeEach: 1 item, locations ['GUDANG','BAR_AREA']
        // -> materializeLines() bikin 2 baris (1 per lokasi) -> 2 <tr>.
        ->and(substr_count($html, 'sticky left-0 z-10 bg-white'))->toBe(2);
});

test('Mode Tabel search filters by item name client-side (matchesSearch reads from the global store, not $parent)', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    $html = $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.show', $document))
        ->assertOk()
        ->getContent();

    // itemName dilempar ke komponen Alpine yang sama dipakai line-card DAN
    // table-row -- membuktikan kedua partial konsisten memakai pola yang
    // sama (bukan 2 implementasi search yang berbeda dan bisa divergen).
    // 2 baris (GUDANG+BAR_AREA) x 2 partial (line-card + table-row) = 4,
    // plus 1 definisi "itemName: config.itemName" di dalam fungsi JS
    // inventoryCountLine() itu sendiri (dicetak sekali per halaman) = 5.
    expect(substr_count($html, 'itemName: '))->toBe(5);
});

test('staff bar can start a draft and staff pastry cannot see or edit it', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $pastryStaff = invUser('STAFF_BAR', 'PASTRY');

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.start'), ['template_id' => $this->barTemplate->id])
        ->assertRedirect();

    $document = InventoryCountDocument::query()->latest('id')->first();

    $this->actingAs($pastryStaff)
        ->get(route('operations.inventory-count.show', $document))
        ->assertForbidden();

    $line = $document->lines()->first();
    $this->actingAs($pastryStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '10', 'expected_version' => $document->version,
        ])
        ->assertForbidden();
});

test('a department without its own template still works via generic fallback template visibility', function (): void {
    $service = Department::query()->where('code', 'SERVICE')->first();
    expect($service)->not->toBeNull();

    InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id,
        'department_id' => null,
        'code' => 'GENERIC_DAILY',
        'name' => 'Generik Harian',
        'frequency' => 'DAILY',
        'locations' => ['GENERIC'],
        'is_generic' => true,
        'is_configured' => false,
    ]);

    $serviceStaff = invUser('STAFF_SERVICE', 'SERVICE');

    $response = $this->actingAs($serviceStaff)->get(route('operations.inventory-count.index'));
    $response->assertOk();

    $templateNames = $response->viewData('templates')->pluck('name')->all();
    expect($templateNames)->toContain('Generik Harian')
        ->and($templateNames)->not->toContain('Bar Harian');
});

// ===================== forged IDs =====================

test('forged outlet_id and department_id in start request are ignored for non-superadmin', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.start'), [
            'template_id' => $this->barTemplate->id,
            'outlet_id' => $this->otherOutlet->id,
            'department_id' => $this->pastry->id,
        ])
        ->assertRedirect();

    $document = InventoryCountDocument::query()->latest('id')->first();

    expect((int) $document->outlet_id)->toBe($this->outlet->id)
        ->and((int) $document->department_id)->toBe($this->bar->id);
});

test('a document from a different tenant is not reachable even for super admin', function (): void {
    $superAdmin = invUser('SUPER_ADMIN', null);
    $foreignOutlet = foreignTenantOutlet();

    $foreignTemplate = InventoryCountTemplate::withoutGlobalScopes()->create([
        'tenant_id' => $foreignOutlet->tenant_id,
        'department_id' => null,
        'code' => 'FOREIGN_TPL',
        'name' => 'Foreign Template',
        'frequency' => 'DAILY',
        'locations' => ['GENERIC'],
        'is_generic' => true,
        'is_configured' => true,
    ]);

    $foreignDocument = InventoryCountDocument::withoutGlobalScopes()->create([
        'tenant_id' => $foreignOutlet->tenant_id,
        'outlet_id' => $foreignOutlet->id,
        'department_id' => 1,
        'template_id' => $foreignTemplate->id,
        'business_date' => now()->toDateString(),
        'status' => 'DRAFT',
        'version' => 1,
        'created_by' => 1,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('operations.inventory-count.show', $foreignDocument))
        ->assertNotFound();
});

test('a forged template_id from another department is rejected on start', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.start'), ['template_id' => $this->pastryTemplate->id])
        ->assertNotFound();
});

// ===================== null/invalid assignment =====================

test('a user with no outlet or department assignment at all fails closed', function (): void {
    $unassigned = invUser('STAFF_BAR', null);
    $unassigned->forceFill(['outlet_id' => null])->saveQuietly();

    $response = $this->actingAs($unassigned)->get(route('operations.inventory-count.index'));
    $response->assertOk();
    expect($response->viewData('hasValidScope'))->toBeFalse();

    $this->actingAs($unassigned)
        ->post(route('operations.inventory-count.start'), ['template_id' => $this->barTemplate->id])
        ->assertForbidden();
});

test('an account with an outlet_id forged to another tenant fails closed, not just a null check', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $foreignOutlet = foreignTenantOutlet();
    $barStaff->forceFill(['outlet_id' => $foreignOutlet->id])->saveQuietly();

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.start'), ['template_id' => $this->barTemplate->id])
        ->assertForbidden();
});

// ===================== super admin =====================

test('super admin can start a draft for any outlet and department within the tenant, but not across tenants', function (): void {
    $superAdmin = invUser('SUPER_ADMIN', null);

    $this->actingAs($superAdmin)
        ->post(route('operations.inventory-count.start'), [
            'template_id' => $this->pastryTemplate->id,
            'outlet_id' => $this->otherOutlet->id,
            'department_id' => $this->pastry->id,
        ])
        ->assertRedirect();

    $document = InventoryCountDocument::query()->latest('id')->first();
    expect((int) $document->outlet_id)->toBe($this->otherOutlet->id);

    $foreignOutlet = foreignTenantOutlet();
    $this->actingAs($superAdmin)
        ->post(route('operations.inventory-count.start'), [
            'template_id' => $this->pastryTemplate->id,
            'outlet_id' => $foreignOutlet->id,
            'department_id' => $this->pastry->id,
        ])
        ->assertForbidden();
});

// ===================== null vs explicit zero, utuh/ecer =====================

test('autosave accepts a comma or a dot as the decimal separator and normalizes storage', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $line = $document->lines()->first();

    // Koma (lazim diketik di Indonesia) harus diterima, BUKAN ditolak
    // validasi "numeric" standar Laravel yang cuma mengenal titik.
    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '12,5', 'expected_version' => $document->version,
        ])
        ->assertOk();

    expect((string) $line->refresh()->qty_whole)->toBe('12.500000');

    $document->refresh();
    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '7.25', 'expected_version' => $document->version,
        ])
        ->assertOk();

    expect((string) $line->refresh()->qty_whole)->toBe('7.250000');
});

test('autosave rejects an ambiguous value with both comma and dot instead of silently guessing', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $line = $document->lines()->first();

    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '1.000,5', 'expected_version' => $document->version,
        ])
        ->assertStatus(422);

    expect($line->refresh()->is_counted)->toBeFalse();
});

test('reloading the show page after autosave reflects the persisted value (resume draft)', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $line = $document->lines()->first();

    $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
        'qty_whole' => '42', 'expected_version' => $document->version,
    ])->assertOk();

    // Simulasi reload: GET ulang halaman show, bukan state client yang
    // dipertahankan -- buktikan nilainya datang dari server (resume),
    // bukan cuma "terlihat tersimpan" di state JS yang sebenarnya hilang
    // kalau tab ditutup.
    $response = $this->actingAs($barStaff)->get(route('operations.inventory-count.show', $document));
    $response->assertOk();

    $persistedLine = $response->viewData('document')->lines->firstWhere('id', $line->id);
    expect((string) $persistedLine->qty_whole)->toBe('42.000000')
        ->and($persistedLine->is_counted)->toBeTrue();
});

test('an untouched line is not counted, but an explicit zero via the zero button is counted', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $line = $document->lines()->first();

    expect($line->is_counted)->toBeFalse();

    $this->actingAs($barStaff)
        ->postJson(route('operations.inventory-count.lines.zero', $line), ['expected_version' => $document->version])
        ->assertOk();

    expect($line->refresh()->is_counted)->toBeTrue()
        ->and((string) $line->qty_whole)->toBe('0.000000');
});

test('autosaving an empty value does not flip is_counted to true', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $line = $document->lines()->first();

    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '', 'expected_version' => $document->version,
        ])
        ->assertOk();

    expect($line->refresh()->is_counted)->toBeFalse();
});

test('a line requiring conversion uses whole and loose quantities independently', function (): void {
    $this->pastryItem->update(['inventory_unit_id' => $this->barItem->inventory_unit_id]);
    $ratioUnit = Unit::query()->where('tenant_id', $this->tenant->id)->where('code', 'PACK')->firstOrFail();
    $this->pastryItem->update(['inventory_unit_id' => $ratioUnit->id, 'inventory_ratio' => '1000.000000']);

    $templateItem = InventoryCountTemplateItem::query()->where('template_id', $this->pastryTemplate->id)->first();
    $templateItem->update(['requires_conversion' => true]);

    $pastryStaff = invUser('STAFF_BAR', 'PASTRY');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->pastry->id, $this->pastryTemplate->fresh(), now(), $pastryStaff->id
    );
    $line = $document->lines()->first();

    expect($line->requires_conversion)->toBeTrue()
        ->and($line->config_valid)->toBeTrue()
        ->and((string) $line->conversion_factor)->toBe('1000.000000');

    $this->actingAs($pastryStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '2', 'qty_loose' => '300', 'expected_version' => $document->version,
        ])
        ->assertOk();

    expect((string) $line->refresh()->qty_base)->toBe('2300.000000');
});

// ===================== autosave/resume, concurrency, submit =====================

test('starting the same template and date twice resumes the same document instead of creating a duplicate', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)->post(route('operations.inventory-count.start'), ['template_id' => $this->barTemplate->id]);
    $firstId = InventoryCountDocument::query()->latest('id')->first()->id;

    $this->actingAs($barStaff)->post(route('operations.inventory-count.start'), ['template_id' => $this->barTemplate->id]);
    $secondId = InventoryCountDocument::query()->latest('id')->first()->id;

    expect($secondId)->toBe($firstId)
        ->and(InventoryCountDocument::query()->count())->toBe(1);
});

test('autosaving with a stale expected_version returns a conflict instead of silently overwriting', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $line = $document->lines()->first();

    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '5', 'expected_version' => $document->version,
        ])
        ->assertOk();

    // "Tab lain" memakai version LAMA yang sudah tidak berlaku lagi.
    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '9', 'expected_version' => $document->version,
        ])
        ->assertStatus(409);

    expect((string) $line->refresh()->qty_whole)->toBe('5.000000');
});

test('submit is blocked while any line is uncounted and reports which ones', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->version])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($document->refresh()->status)->toBe('DRAFT');
});

test('submit is blocked when a line requires conversion but the item is not configured for it', function (): void {
    $this->pastryTemplate->update(['is_configured' => true]);
    $templateItem = InventoryCountTemplateItem::query()->where('template_id', $this->pastryTemplate->id)->first();
    $templateItem->update(['requires_conversion' => true]); // tapi item master belum punya rasio valid.

    $pastryStaff = invUser('STAFF_BAR', 'PASTRY');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->pastry->id, $this->pastryTemplate->fresh(), now(), $pastryStaff->id
    );
    $line = $document->lines()->first();
    expect($line->config_valid)->toBeFalse();

    $this->actingAs($pastryStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '5', 'expected_version' => $document->version,
        ]);

    $this->actingAs($pastryStaff)
        ->post(route('operations.inventory-count.submit', $document->refresh()), ['expected_version' => $document->version])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($document->refresh()->status)->toBe('DRAFT');
});

test('submitting successfully then editing is blocked until a revision is requested', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $manager = invUser('MANAGER_AREA', null);
    $manager->forceFill(['outlet_id' => $this->outlet->id, 'department_id' => $this->bar->id])->saveQuietly();

    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    // barTemplate punya 2 lokasi (GUDANG + BAR_AREA) untuk item ini -- jadi
    // 2 baris. Submit() memvalidasi SEMUA baris, jadi keduanya harus diisi.
    foreach ($document->lines as $line) {
        $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '5', 'expected_version' => $document->refresh()->version,
        ])->assertOk();
    }

    $document->refresh();
    $firstLine = $document->lines->first();
    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->version])
        ->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe('SUBMITTED');

    // Tidak bisa diedit langsung setelah submitted.
    $this->actingAs($barStaff)
        ->patchJson(route('operations.inventory-count.lines.autosave', $firstLine), [
            'qty_whole' => '7', 'expected_version' => $document->version,
        ])
        ->assertStatus(409);

    // Revisi berizin dengan alasan tercatat.
    $this->actingAs($manager)
        ->post(route('operations.inventory-count.request-revision', $document), ['reason' => 'Salah ketik'])
        ->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe('DRAFT')
        ->and($document->revisions()->count())->toBe(1)
        ->and($document->revisions()->first()->reason)->toBe('Salah ketik');
});

// ===================== item shared, tidak memposting saldo =====================

test('the same shared item observed by two departments stays as independent lines, not merged or summed', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $pastryStaff = invUser('STAFF_BAR', 'PASTRY');

    // Template PASTRY juga mengamati item barItem yang sama (shared).
    $sharedTemplateItem = InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'template_id' => $this->pastryTemplate->id,
        'item_id' => $this->barItem->id,
        'subcategory_label' => 'BUMBU',
        'sort_order' => 2,
        'locations' => ['GENERIC'],
        'requires_conversion' => false,
        'is_active' => true,
    ]);

    $barDoc = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );
    $pastryDoc = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->pastry->id, $this->pastryTemplate, now(), $pastryStaff->id
    );

    $barLine = $barDoc->lines()->where('item_id', $this->barItem->id)->first();
    $pastryLine = $pastryDoc->lines()->where('item_id', $this->barItem->id)->first();

    expect($barLine->id)->not->toBe($pastryLine->id);

    $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $barLine), [
        'qty_whole' => '30', 'expected_version' => $barDoc->version,
    ]);
    $this->actingAs($pastryStaff)->patchJson(route('operations.inventory-count.lines.autosave', $pastryLine), [
        'qty_whole' => '70', 'expected_version' => $pastryDoc->version,
    ]);

    expect((string) $barLine->refresh()->qty_whole)->toBe('30.000000')
        ->and((string) $pastryLine->refresh()->qty_whole)->toBe('70.000000');
});

test('create, autosave, submit, and revision never touch stock_balances or stock_mutations', function (): void {
    $balanceSnapshot = StockBalance::query()->orderBy('id')->get(['id', 'qty_on_hand'])->toArray();
    $mutationCountBefore = StockMutation::query()->count();

    $barStaff = invUser('STAFF_BAR', 'BAR');
    $manager = invUser('MANAGER_AREA', null);
    $manager->forceFill(['outlet_id' => $this->outlet->id, 'department_id' => $this->bar->id])->saveQuietly();

    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    foreach ($document->lines as $line) {
        $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '12', 'expected_version' => $document->refresh()->version,
        ])->assertOk();
    }

    $document->refresh();
    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->version])
        ->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe('SUBMITTED');

    $this->actingAs($manager)
        ->post(route('operations.inventory-count.request-revision', $document), ['reason' => 'cek ulang'])
        ->assertRedirect();

    expect($document->refresh()->status)->toBe('DRAFT');

    $mutationCountAfter = StockMutation::query()->count();
    $balanceSnapshotAfter = StockBalance::query()->orderBy('id')->get(['id', 'qty_on_hand'])->toArray();

    expect($mutationCountAfter)->toBe($mutationCountBefore)
        ->and($balanceSnapshotAfter)->toBe($balanceSnapshot);
});

// ===================== export =====================

test('export is forced to the acting staff own outlet and department regardless of query string', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');

    app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    $response = $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.export', ['outlet_id' => $this->otherOutlet->id, 'department_id' => $this->pastry->id]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
});

test('export is forbidden without a valid scope', function (): void {
    $unassigned = invUser('STAFF_BAR', null);
    $unassigned->forceFill(['outlet_id' => null])->saveQuietly();

    $this->actingAs($unassigned)
        ->get(route('operations.inventory-count.export'))
        ->assertForbidden();
});

// ===================== feature flag OFF =====================

/**
 * config('app.inventory_simple_enabled') dibaca SEKALI saat routes/web.php
 * dieksekusi pada boot aplikasi -- mengubah config/env DI TENGAH request
 * test yang sama TIDAK membatalkan route yang sudah terlanjur terdaftar.
 * Satu-satunya cara jujur menguji "route sama sekali tidak terdaftar saat
 * flag OFF" adalah boot PROSES PHP BARU dengan env itu di-override dari
 * awal -- makanya test ini spawn `php artisan route:list` sebagai child
 * process, bukan lewat HTTP test di proses yang sama.
 */
test('every inventory-count route is completely unregistered (not just forbidden) when the feature flag is off', function (): void {
    $phpBinary = PHP_BINARY;
    $basePath = base_path();

    $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $env = array_filter(array_merge($_ENV, $_SERVER), fn ($value) => is_scalar($value));
    $env['INVENTORY_SIMPLE_ENABLED'] = 'false';

    $process = proc_open(
        "\"{$phpBinary}\" artisan route:list --name=inventory-count",
        $descriptorSpec,
        $pipes,
        $basePath,
        $env
    );

    expect($process)->not->toBeFalse();

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    // "Your application doesn't have any routes matching the given name."
    // ATAU tabel kosong -- intinya TIDAK ADA satu pun baris route
    // inventory-count.* yang muncul, beda dari 403 (yang masih berarti
    // route terdaftar, cuma ditolak otorisasi).
    expect($stdout)->not->toContain('operations.inventory-count.index')
        ->and($stdout)->not->toContain('operations.inventory-count.show')
        ->and($stdout)->not->toContain('operations.inventory-count.lines.autosave')
        ->and($stdout)->not->toContain('operations.inventory-count.submit')
        ->and($stdout)->not->toContain('operations.inventory-count.request-revision')
        ->and($stdout)->not->toContain('operations.inventory-count.export');
})->skip(fn () => ! file_exists(base_path('artisan')), 'artisan tidak ditemukan di base_path() -- lingkungan test tidak mendukung subprocess check ini.');

// ===================== business_date tidak pernah bergeser =====================
// Regresi khusus untuk UAT 2026-10-13 (G.6): skrip pengujian browser
// sempat (karena bug SKRIP, bukan produk) mengubah business_date salah
// satu dokumen uji secara tidak sengaja. Dikonfirmasi bersih lewat 2
// reproduksi terisolasi (panggil service langsung + HTTP mentah), tapi
// belum ada test permanen yang mengunci invariant ini -- ditambahkan di
// sini supaya regresi serupa di masa depan tertangkap otomatis.

test('business_date tidak berubah melalui siklus penuh autosave, submit, revisi, dan submit ulang', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $manager = invUser('MANAGER_AREA', null);
    $manager->forceFill(['outlet_id' => $this->outlet->id, 'department_id' => $this->bar->id])->saveQuietly();

    $originalDate = now()->subDays(3)->startOfDay();
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, $originalDate, $barStaff->id
    );
    $expectedDateString = $document->business_date->toDateString();
    expect($expectedDateString)->toBe($originalDate->toDateString());

    foreach ($document->lines as $line) {
        $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '5', 'expected_version' => $document->refresh()->version,
        ])->assertOk();
        expect($document->refresh()->business_date->toDateString())->toBe($expectedDateString);
    }

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->refresh()->version])
        ->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe('SUBMITTED')
        ->and($document->business_date->toDateString())->toBe($expectedDateString);

    $this->actingAs($manager)
        ->post(route('operations.inventory-count.request-revision', $document), ['reason' => 'Cek ulang angka'])
        ->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe('DRAFT')
        ->and($document->business_date->toDateString())->toBe($expectedDateString);

    $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $document->refresh()->version])
        ->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe('SUBMITTED')
        ->and($document->business_date->toDateString())->toBe($expectedDateString)
        // Nilai mentah di DB juga dicek, bukan cuma hasil cast Carbon --
        // supaya kalau cast 'date' bare ikut membengkak jadi
        // "Y-m-d H:i:s" (pola bug D-F1 di movements), tetap ketahuan
        // selama bagian tanggalnya (10 char pertama) tidak berubah.
        ->and(substr($document->getRawOriginal('business_date'), 0, 10))->toBe($expectedDateString);
});

test('klik ganda (double-click) tombol Kirim tidak menduplikasi dokumen atau menyebabkan error server', function (): void {
    $barStaff = invUser('STAFF_BAR', 'BAR');
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, now(), $barStaff->id
    );

    foreach ($document->lines as $line) {
        $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
            'qty_whole' => '5', 'expected_version' => $document->refresh()->version,
        ])->assertOk();
    }

    $document->refresh();
    $expectedVersion = $document->version;

    // Dua request submit BERURUTAN dengan expected_version yang SAMA --
    // mensimulasikan klik ganda sebelum tombol sempat disabled/redirect
    // pertama selesai. Request pertama harus berhasil; yang kedua TIDAK
    // BOLEH membuat dokumen baru atau crash 500 -- cukup gagal dgn pesan
    // konflik yang rapi (versi sudah tidak berlaku lagi setelah submit
    // pertama menaikkan version), persis seperti autosave stale-version.
    $first = $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $expectedVersion]);
    $second = $this->actingAs($barStaff)
        ->post(route('operations.inventory-count.submit', $document), ['expected_version' => $expectedVersion]);

    $first->assertRedirect();
    expect($second->status())->not->toBe(500);

    $document->refresh();
    expect($document->status)->toBe('SUBMITTED')
        ->and(InventoryCountDocument::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});
