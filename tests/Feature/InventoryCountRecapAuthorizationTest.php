<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\InventoryCountDocument;
use App\Modules\Operations\Models\InventoryCountTemplate;
use App\Modules\Operations\Models\InventoryCountTemplateItem;
use App\Services\InventoryCountService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Otorisasi Rekap Hasil Hitung Mingguan (read-only) -- scope lintas
 * tenant/departemen, forged query param, akun tanpa assignment,
 * SUPER_ADMIN lintas departemen dalam tenant sendiri, dan template
 * generic tidak membocorkan item departemen lain lewat forged template_id.
 */
beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->outlet = Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->pastry = Department::query()->where('code', 'PASTRY')->firstOrFail();
    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->pastryItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();

    $this->barTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id, 'department_id' => $this->bar->id,
        'code' => 'BAR_DAILY', 'name' => 'Bar Harian', 'frequency' => 'DAILY',
        'locations' => ['GENERIC'], 'is_generic' => false, 'is_configured' => true,
    ]);
    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id, 'template_id' => $this->barTemplate->id, 'item_id' => $this->barItem->id,
        'subcategory_label' => 'BUMBU', 'sort_order' => 1, 'locations' => ['GENERIC'],
        'requires_conversion' => false, 'is_active' => true,
    ]);

    $this->pastryTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id, 'department_id' => $this->pastry->id,
        'code' => 'PASTRY_DAILY', 'name' => 'Pastry Harian', 'frequency' => 'DAILY',
        'locations' => ['GENERIC'], 'is_generic' => false, 'is_configured' => true,
    ]);
    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id, 'template_id' => $this->pastryTemplate->id, 'item_id' => $this->pastryItem->id,
        'subcategory_label' => 'BAKING', 'sort_order' => 1, 'locations' => ['GENERIC'],
        'requires_conversion' => false, 'is_active' => true,
    ]);

    // Template GENERIC: boleh diakses departemen manapun, tapi item di
    // dalamnya HARUS tetap mandiri (bukan gabungan item semua departemen).
    $this->genericTemplate = InventoryCountTemplate::query()->create([
        'tenant_id' => $this->tenant->id, 'department_id' => null,
        'code' => 'GENERIC_DAILY', 'name' => 'Generik Harian', 'frequency' => 'DAILY',
        'locations' => ['GENERIC'], 'is_generic' => true, 'is_configured' => true,
    ]);
    InventoryCountTemplateItem::query()->create([
        'tenant_id' => $this->tenant->id, 'template_id' => $this->genericTemplate->id, 'item_id' => $this->barItem->id,
        'subcategory_label' => 'UMUM', 'sort_order' => 1, 'locations' => ['GENERIC'],
        'requires_conversion' => false, 'is_active' => true,
    ]);
});

function recapUser(string $roleName, ?string $departmentCode, ?Outlet $outlet = null): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet ??= Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $department = $departmentCode ? Department::query()->where('code', $departmentCode)->firstOrFail() : null;

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $roleName === 'SUPER_ADMIN' ? null : $outlet->id,
        'department_id' => $department?->id,
        'status' => 'ACTIVE',
        'email' => strtolower($roleName.'_recap_'.($departmentCode ?? 'nodept').'_'.uniqid()).'@sifobi.test',
    ]);
    $user->assignRole($roleName);

    return $user;
}

test('non-superadmin forged department_id/outlet_id query params are ignored, own scope always used', function (): void {
    $barStaff = recapUser('STAFF_BAR', 'BAR');

    $response = $this->actingAs($barStaff)->get(route('operations.inventory-count.recap.index', [
        'template_id' => $this->barTemplate->id,
        // forged -- coba intip departemen PASTRY lewat query string.
        'department_id' => $this->pastry->id,
        'outlet_id' => $this->outlet->id,
    ]));

    $response->assertOk();
    $response->assertSeeText($this->barItem->name);
    $response->assertDontSeeText($this->pastryItem->name);
});

test('forged template_id pointing to another department template is rejected, not leaked', function (): void {
    $barStaff = recapUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.recap.index', ['template_id' => $this->pastryTemplate->id]))
        ->assertNotFound();
});

test('generic template is reachable by any department but stays self-contained, not merged with department templates', function (): void {
    $barStaff = recapUser('STAFF_BAR', 'BAR');
    $pastryStaff = recapUser('STAFF_BAR', 'PASTRY');

    foreach ([$barStaff, $pastryStaff] as $user) {
        $response = $this->actingAs($user)
            ->get(route('operations.inventory-count.recap.index', ['template_id' => $this->genericTemplate->id]));
        $response->assertOk();
        $response->assertSeeText($this->barItem->name); // satu-satunya item di template generic ini
        $response->assertDontSeeText($this->pastryItem->name); // TIDAK ikut tergabung
    }
});

test('an account with no outlet or department assignment at all cannot view recap', function (): void {
    $noAssignment = recapUser('STAFF_BAR', null);

    $this->actingAs($noAssignment)
        ->get(route('operations.inventory-count.recap.index', ['template_id' => $this->barTemplate->id]))
        ->assertForbidden();
});

test('super admin can view recap for any department within own tenant via explicit params', function (): void {
    $admin = recapUser('SUPER_ADMIN', null);

    $response = $this->actingAs($admin)->get(route('operations.inventory-count.recap.index', [
        'template_id' => $this->pastryTemplate->id,
        'outlet_id' => $this->outlet->id,
        'department_id' => $this->pastry->id,
    ]));

    $response->assertOk();
    $response->assertSeeText($this->pastryItem->name);
});

test('super admin forging an outlet or department from another tenant is rejected', function (): void {
    $admin = recapUser('SUPER_ADMIN', null);
    $otherOutlet = foreignTenantOutletForRecap();

    $this->actingAs($admin)->get(route('operations.inventory-count.recap.index', [
        'template_id' => $this->barTemplate->id,
        'outlet_id' => $otherOutlet->id,
        'department_id' => $this->bar->id,
    ]))->assertForbidden();
});

test('a template and its items from a different tenant are completely unreachable, not just empty', function (): void {
    $barStaff = recapUser('STAFF_BAR', 'BAR');
    $otherOutlet = foreignTenantOutletForRecap();

    $otherTemplate = InventoryCountTemplate::withoutGlobalScopes()->create([
        'tenant_id' => $otherOutlet->tenant_id, 'department_id' => null,
        'code' => 'OTHER_TEMPLATE', 'name' => 'Template Tenant Lain', 'frequency' => 'DAILY',
        'locations' => ['GENERIC'], 'is_generic' => true, 'is_configured' => true,
    ]);

    $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.recap.index', ['template_id' => $otherTemplate->id]))
        ->assertNotFound();
});

function foreignTenantOutletForRecap(): Outlet
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

test('export respects the exact same scope as the page -- forged params produce 404, not another department data', function (): void {
    $barStaff = recapUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)
        ->get(route('operations.inventory-count.recap.export', ['template_id' => $this->pastryTemplate->id]))
        ->assertNotFound();
});

test('recap only shows the currently-valid submitted version after a revision, matching the UI-level UAT finding', function (): void {
    $barStaff = recapUser('STAFF_BAR', 'BAR');
    $manager = recapUser('MANAGER_AREA', null);
    $manager->forceFill(['outlet_id' => $this->outlet->id, 'department_id' => $this->bar->id])->saveQuietly();

    $date = now()->startOfWeek()->addDay(); // Selasa minggu ini, dalam window recap default
    $document = app(InventoryCountService::class)->createOrResumeDraft(
        $this->tenant->id, $this->outlet->id, $this->bar->id, $this->barTemplate, $date, $barStaff->id
    );
    $line = $document->lines()->first();
    $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
        'qty_whole' => '10', 'expected_version' => $document->refresh()->version,
    ])->assertOk();
    $this->actingAs($barStaff)->post(route('operations.inventory-count.submit', $document->refresh()), [
        'expected_version' => $document->version,
    ])->assertRedirect();

    $this->actingAs($manager)->post(route('operations.inventory-count.request-revision', $document->refresh()), [
        'reason' => 'ganti angka',
    ])->assertRedirect();

    $this->actingAs($barStaff)->patchJson(route('operations.inventory-count.lines.autosave', $line), [
        'qty_whole' => '77', 'expected_version' => $document->refresh()->version,
    ])->assertOk();
    $this->actingAs($barStaff)->post(route('operations.inventory-count.submit', $document->refresh()), [
        'expected_version' => $document->version,
    ])->assertRedirect();

    $response = $this->actingAs($barStaff)->get(route('operations.inventory-count.recap.index', [
        'template_id' => $this->barTemplate->id,
    ]));
    $response->assertOk();
    $response->assertSeeText('77.000000');
    $response->assertDontSeeText('10.000000');
});
