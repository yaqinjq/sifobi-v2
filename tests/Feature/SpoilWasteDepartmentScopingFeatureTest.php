<?php

use App\Models\User;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use App\Modules\Stock\Models\StockMutation;
use App\Services\SpoilWasteService;
use App\Services\StockLedgerService;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/**
 * Kebijakan yang diuji di file ini: HANYA role SUPER_ADMIN boleh lintas
 * tenant/outlet/departemen di modul Spoil & Waste. Permission
 * `approve_spoil`/`view_reports` adalah izin melakukan aksi (approve,
 * lihat laporan), BUKAN izin melewati scope -- jadi PIC_OUTLET/
 * MANAGER_AREA (desainnya tidak terikat 1 departemen) sekarang FAIL
 * CLOSED di modul ini (lihat laporan workflow yang terdampak).
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
    $this->service = Department::query()->where('code', 'SERVICE')->firstOrFail();

    $this->barItem = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();
    $this->barItem->update(['primary_department_id' => $this->bar->id]);

    $this->pastryItem = Item::query()->where('canonical_sku', 'MKO-GULA-PASIR')->firstOrFail();
    $this->pastryItem->update(['primary_department_id' => $this->pastry->id]);

    foreach ([$this->outlet, $this->otherOutlet] as $outlet) {
        foreach ([$this->barItem, $this->pastryItem] as $item) {
            app(StockLedgerService::class)->openStock([
                'tenant_id' => $this->tenant->id,
                'outlet_id' => $outlet->id,
                'item_id' => $item->id,
                'unit_id' => $item->base_unit_id,
                'stock_target' => StockMutation::TARGET_OUTLET_DAILY,
                'qty' => '1000',
                'performed_by' => User::query()->where('email', 'admin@sifobi.test')->value('id'),
            ]);
        }
    }
});

function spoilUser(string $roleName, ?string $departmentCode, ?Outlet $outlet = null): User
{
    $tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $outlet ??= Outlet::query()->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $department = $departmentCode ? Department::query()->where('code', $departmentCode)->firstOrFail() : null;

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'outlet_id' => $roleName === 'SUPER_ADMIN' ? null : $outlet->id,
        'department_id' => $department?->id,
        'name' => $roleName.' '.($departmentCode ?? 'NODEPT').' User',
        'email' => strtolower($roleName.'_spoil_'.($departmentCode ?? 'nodept').'_'.uniqid()).'@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    $user->assignRole($roleName);

    return $user;
}

function recordSpoilFor(User $user, Outlet $outlet, Department $department, Item $item, string $qtyWhole = '1'): SpoilWaste
{
    return app(SpoilWasteService::class)->record([
        'tenant_id' => $user->tenant_id,
        'outlet_id' => $outlet->id,
        'department_id' => $department->id,
        'item_id' => $item->id,
        'qty_whole' => $qtyWhole,
        'qty_loose' => '0',
        'recorded_date' => now()->toDateString(),
        'reason_category' => SpoilWaste::REASON_RUSAK,
    ], $user->id);
}

// ===================== list/detail/search =====================

test('index only shows spoil waste from the staff own department, excluding legacy null department records', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $pastrySpoil = recordSpoilFor($pastryStaff, $this->outlet, $this->pastry, $this->pastryItem);

    $legacySpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $legacySpoil->forceFill(['department_id' => null])->saveQuietly();

    $response = $this->actingAs($pastryStaff)->get(route('operations.spoil-wastes.index'));
    $response->assertOk();

    $ids = $response->viewData('spoilWastes')->getCollection()->pluck('id')->all();

    expect($ids)->toContain($pastrySpoil->id)
        ->and($ids)->not->toContain($barSpoil->id)
        ->and($ids)->not->toContain($legacySpoil->id);
});

test('index returns nothing for pic_outlet because it has no department assignment (fail closed, not auto-admin)', function (): void {
    Storage::fake('local');
    $pic = spoilUser('PIC_OUTLET', null);
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $response = $this->actingAs($pic)->get(route('operations.spoil-wastes.index'));
    $response->assertOk();

    expect($response->viewData('spoilWastes')->getCollection())->toBeEmpty();
});

test('super admin sees spoil across all departments and outlets', function (): void {
    Storage::fake('local');
    $superAdmin = spoilUser('SUPER_ADMIN', null);
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY', $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $pastrySpoil = recordSpoilFor($pastryStaff, $this->otherOutlet, $this->pastry, $this->pastryItem);

    $response = $this->actingAs($superAdmin)->get(route('operations.spoil-wastes.index'));
    $ids = $response->viewData('spoilWastes')->getCollection()->pluck('id')->all();

    expect($ids)->toContain($barSpoil->id)
        ->and($ids)->toContain($pastrySpoil->id);
});

test('opening another departments spoil detail page directly by url is forbidden', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($pastryStaff)
        ->get(route('operations.spoil-wastes.show', $barSpoil))
        ->assertForbidden();
});

test('a legacy spoil record with null department is not visible to department bound staff', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');

    $legacySpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $legacySpoil->forceFill(['department_id' => null])->saveQuietly();

    $this->actingAs($barStaff)
        ->get(route('operations.spoil-wastes.show', $legacySpoil))
        ->assertForbidden();
});

test('opening a spoil detail page from another outlet is forbidden even for pic_outlet', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($picOtherOutlet)
        ->get(route('operations.spoil-wastes.show', $barSpoil))
        ->assertForbidden();
});

test('super admin can open any spoil detail page regardless of department or outlet', function (): void {
    Storage::fake('local');
    $superAdmin = spoilUser('SUPER_ADMIN', null);
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($superAdmin)
        ->get(route('operations.spoil-wastes.show', $barSpoil))
        ->assertOk();
});

test('search items only returns items belonging to the staff own department', function (): void {
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $response = $this->actingAs($pastryStaff)
        ->getJson(route('operations.spoil-wastes.search-items', ['q' => 'MKO']));

    $response->assertOk();
    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)->toContain($this->pastryItem->id)
        ->and($ids)->not->toContain($this->barItem->id);
});

test('search items returns nothing for pic_outlet (no department assignment)', function (): void {
    $pic = spoilUser('PIC_OUTLET', null);

    $response = $this->actingAs($pic)
        ->getJson(route('operations.spoil-wastes.search-items', ['q' => 'MKO']));

    $response->assertOk();
    expect($response->json())->toBe([]);
});

// ===================== store =====================

test('store ignores a forged department_id and outlet_id, always uses the staff own account values', function (): void {
    Storage::fake('local');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $this->actingAs($pastryStaff)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->otherOutlet->id,
            'department_id' => $this->bar->id,
            'item_id' => $this->pastryItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ])
        ->assertRedirect();

    $spoil = SpoilWaste::query()->latest('id')->first();

    expect((int) $spoil->department_id)->toBe($this->pastry->id)
        ->and((int) $spoil->outlet_id)->toBe($this->outlet->id);
});

test('store rejects an item that does not belong to the staff own department', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');

    $this->actingAs($barStaff)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'item_id' => $this->pastryItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ])
        ->assertSessionHasErrors('item_id');

    expect(SpoilWaste::query()->where('item_id', $this->pastryItem->id)->exists())->toBeFalse();
});

test('pic_outlet cannot record any spoil at all (no department assignment, fail closed)', function (): void {
    Storage::fake('local');
    $pic = spoilUser('PIC_OUTLET', null);

    $this->actingAs($pic)
        ->get(route('operations.spoil-wastes.create'))
        ->assertForbidden();

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'item_id' => $this->barItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ])
        ->assertForbidden();
});

test('super admin can record spoil for any outlet and department', function (): void {
    Storage::fake('local');
    $superAdmin = spoilUser('SUPER_ADMIN', null);

    $this->actingAs($superAdmin)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->pastry->id,
            'item_id' => $this->pastryItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
        ])
        ->assertRedirect();

    expect(SpoilWaste::query()->where('item_id', $this->pastryItem->id)->where('department_id', $this->pastry->id)->exists())->toBeTrue();
});

// ===================== approve / reject =====================

test('pic_outlet can no longer approve any spoil because it has no department assignment', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pic = spoilUser('PIC_OUTLET', null);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.approve', $barSpoil), ['approval_notes' => 'ok'])
        ->assertForbidden();

    expect($barSpoil->refresh()->status)->toBe(SpoilWaste::STATUS_PENDING);
});

test('approving a spoil from another outlet is forbidden even with approve_spoil permission', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($picOtherOutlet)
        ->post(route('operations.spoil-wastes.approve', $barSpoil), ['approval_notes' => 'test'])
        ->assertForbidden();

    expect($barSpoil->refresh()->status)->toBe(SpoilWaste::STATUS_PENDING);
});

test('super admin can approve spoil from any department and outlet', function (): void {
    Storage::fake('local');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');
    $superAdmin = spoilUser('SUPER_ADMIN', null);

    $pastrySpoil = recordSpoilFor($pastryStaff, $this->outlet, $this->pastry, $this->pastryItem);

    $this->actingAs($superAdmin)
        ->post(route('operations.spoil-wastes.approve', $pastrySpoil), ['approval_notes' => 'ok'])
        ->assertRedirect();

    expect($pastrySpoil->refresh()->status)->toBe(SpoilWaste::STATUS_APPROVED);
});

test('rejecting a spoil from another outlet is forbidden', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);

    $barSpoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($picOtherOutlet)
        ->post(route('operations.spoil-wastes.reject', $barSpoil), ['approval_notes' => 'test'])
        ->assertForbidden();
});

// ===================== bulk approve =====================

test('bulk approve with a mix of allowed and out-of-scope ids only processes the allowed ones and reports the rest', function (): void {
    // Di bawah kebijakan baru, satu-satunya non-superadmin yang bisa
    // mencapai bulkApprove() DENGAN scope valid (bukan fail-closed total)
    // adalah akun yang punya outlet_id+department_id sah DAN permission
    // approve_spoil diberikan langsung (bukan lewat role PIC_OUTLET/
    // MANAGER_AREA yang desainnya tidak terikat departemen). Ini
    // menyimulasikan grant langsung semacam itu untuk membuktikan
    // mekanisme "mix ID sah vs di luar scope" benar-benar bekerja.
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');

    $hybridApprover = spoilUser('STAFF_BAR', 'BAR');
    $hybridApprover->givePermissionTo('approve_spoil');

    $allowedSpoil1 = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $allowedSpoil2 = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $outOfScopeSpoil = recordSpoilFor($pastryStaff, $this->outlet, $this->pastry, $this->pastryItem);

    $response = $this->actingAs($hybridApprover)
        ->post(route('operations.spoil-wastes.bulk-approve'), [
            'ids' => [$allowedSpoil1->id, $allowedSpoil2->id, $outOfScopeSpoil->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', fn ($message) => str_contains($message, (string) $outOfScopeSpoil->id) && str_contains($message, 'luar scope'));

    expect($allowedSpoil1->refresh()->status)->toBe(SpoilWaste::STATUS_APPROVED)
        ->and($allowedSpoil2->refresh()->status)->toBe(SpoilWaste::STATUS_APPROVED)
        ->and($outOfScopeSpoil->refresh()->status)->toBe(SpoilWaste::STATUS_PENDING);
});

test('bulk approve by a department bound staff (no approve_spoil) is blocked by route permission entirely', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $spoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $this->actingAs($barStaff)
        ->post(route('operations.spoil-wastes.bulk-approve'), ['ids' => [$spoil->id]])
        ->assertForbidden();
});

// ===================== import =====================

test('import form and submission are restricted to super admin only', function (): void {
    $pic = spoilUser('PIC_OUTLET', null);

    $this->actingAs($pic)->get(route('operations.spoil-wastes.import-form'))->assertForbidden();

    $path = storage_path('framework/testing/spoil-import-forbidden.xlsx');
    \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
        ['outlet', 'sku', 'unit', 'qty', 'tanggal_kejadian', 'kategori_alasan'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $this->actingAs($pic)
        ->post(route('operations.spoil-wastes.import'), ['file' => new UploadedFile($path, 'import.xlsx', null, null, true)])
        ->assertForbidden();
});

test('import rejects rows referencing an outlet or department from another tenant', function (): void {
    $superAdmin = spoilUser('SUPER_ADMIN', null);

    $otherTenant = Tenant::query()->create(['code' => 'OTHERTEN2', 'name' => 'Other Tenant 2', 'status' => 'ACTIVE']);
    $otherBrand = Brand::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTB2', 'name' => 'Other Brand 2', 'status' => 'ACTIVE']);
    $otherLegalEntity = LegalEntity::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTLE2', 'name' => 'Other Legal 2', 'status' => 'ACTIVE']);
    $otherOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id, 'brand_id' => $otherBrand->id, 'legal_entity_id' => $otherLegalEntity->id,
        'code' => 'FOREIGN_OUTLET', 'name' => 'Foreign Outlet', 'status' => 'ACTIVE',
    ]);

    $import = new \App\Imports\SpoilWasteImport($this->tenant->id, $superAdmin->id, app(SpoilWasteService::class));

    $path = storage_path('framework/testing/spoil-import-forged.xlsx');
    \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
        ['outlet', 'sku', 'unit', 'qty', 'tanggal_kejadian', 'kategori_alasan'],
        [$otherOutlet->code, $this->barItem->canonical_sku, 'GR', '1', now()->toDateString(), 'Rusak/Cacat'],
        ['NONEXISTENT_ITEM_SKU_12345', $this->barItem->canonical_sku, 'GR', '1', now()->toDateString(), 'Rusak/Cacat'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $import->import($path);
    $summary = $import->summary();

    expect($summary['inserted'])->toBe(0)
        ->and($summary['failed'])->toBe(2)
        ->and($summary['errors'][0]['message'])->toContain('tidak ditemukan');
});

// ===================== notifications =====================

test('recording a spoil does not notify approvers outside the item departments scope', function (): void {
    Storage::fake('local');
    \Illuminate\Support\Facades\Notification::fake();

    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $picSameOutlet = spoilUser('PIC_OUTLET', null);
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);
    $superAdmin = spoilUser('SUPER_ADMIN', null);

    recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    // usersForApproval() (NotificationRecipientResolver, pre-existing,
    // shared dengan modul lain, SENGAJA TIDAK diubah di patch ini) masih
    // memakai aturan lama "department_id null = dianggap bisa handle
    // semua" -- BUKAN kebijakan baru "hanya superadmin eksplisit".
    //
    // - picOtherOutlet (outlet BEDA): benar tidak dapat notifikasi --
    //   resolver tetap menghormati outlet_id.
    // - superAdmin: dapat notifikasi (benar, sesuai kebijakan baru juga).
    // - picSameOutlet (outlet SAMA, departemen null): MASIH dapat
    //   notifikasi di bawah resolver lama -- padahal di bawah kebijakan
    //   BARU, PIC_OUTLET ini TIDAK BISA lagi membuka/approve spoil itu
    //   sama sekali (403, lihat test lain). Ini GAP yang disengaja belum
    //   ditutup pada patch ini (lihat laporan review) -- notifikasi
    //   "mengundang" ke aksi yang sekarang diblokir.
    \Illuminate\Support\Facades\Notification::assertSentTo($superAdmin, \App\Notifications\WorkflowNotification::class);
    \Illuminate\Support\Facades\Notification::assertSentTo($picSameOutlet, \App\Notifications\WorkflowNotification::class);
    \Illuminate\Support\Facades\Notification::assertNotSentTo($picOtherOutlet, \App\Notifications\WorkflowNotification::class);
});

// ===================== photo access =====================

test('new spoil photos are stored privately and are not reachable via the old public storage url', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');

    $photo = UploadedFile::fake()->image('bukti.jpg', 300, 300);

    $this->actingAs($barStaff)
        ->post(route('operations.spoil-wastes.store'), [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->bar->id,
            'item_id' => $this->barItem->id,
            'qty_whole' => '1',
            'qty_loose' => '0',
            'recorded_date' => now()->toDateString(),
            'reason_category' => SpoilWaste::REASON_RUSAK,
            'photo' => $photo,
        ])
        ->assertRedirect();

    $spoil = SpoilWaste::query()->latest('id')->first();

    expect($spoil->photo)->not->toBeNull();
    Storage::disk('local')->assertExists($spoil->photo);
    Storage::disk('public')->assertMissing($spoil->photo);
});

test('photo endpoint requires login', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $spoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $spoil->forceFill(['photo' => 'tenants/1/spoil/dummy.jpg'])->saveQuietly();
    Storage::disk('local')->put('tenants/1/spoil/dummy.jpg', 'fake-image-content');

    $this->get(route('operations.spoil-wastes.photo', $spoil))
        ->assertRedirect('/login');
});

test('photo endpoint is forbidden for a user outside the spoil scope (department, outlet, tenant)', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $pastryStaff = spoilUser('STAFF_BAR', 'PASTRY');
    $picOtherOutlet = spoilUser('PIC_OUTLET', null, $this->otherOutlet);

    $spoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $spoil->forceFill(['photo' => 'tenants/1/spoil/dummy.jpg'])->saveQuietly();
    Storage::disk('local')->put('tenants/1/spoil/dummy.jpg', 'fake-image-content');

    $this->actingAs($pastryStaff)->get(route('operations.spoil-wastes.photo', $spoil))->assertForbidden();
    $this->actingAs($picOtherOutlet)->get(route('operations.spoil-wastes.photo', $spoil))->assertForbidden();
});

test('photo endpoint allows the owning department staff and super admin', function (): void {
    Storage::fake('local');
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    $superAdmin = spoilUser('SUPER_ADMIN', null);

    $spoil = recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);
    $spoil->forceFill(['photo' => 'tenants/1/spoil/dummy.jpg'])->saveQuietly();
    Storage::disk('local')->put('tenants/1/spoil/dummy.jpg', 'fake-image-content');

    $this->actingAs($barStaff)->get(route('operations.spoil-wastes.photo', $spoil))->assertOk();
    $this->actingAs($superAdmin)->get(route('operations.spoil-wastes.photo', $spoil))->assertOk();
});

// ===================== report / export =====================

test('spoil report and export force the staff own department and outlet regardless of query string', function (): void {
    Storage::fake('local');
    $serviceStaff = spoilUser('STAFF_SERVICE', 'SERVICE');
    // STAFF_SERVICE (dan STAFF_GUDANG) punya view_reports (beda dari
    // STAFF_BAR/KITCHEN) -- pastikan tetap tidak bisa lihat/export
    // departemen/outlet lain lewat query string, dan BUKAN superadmin
    // jadi scope-nya dipaksa ke outlet+departemen akun sendiri.
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $response = $this->actingAs($serviceStaff)
        ->get(route('laporan.spoil', ['department_id' => $this->bar->id, 'outlet_id' => $this->otherOutlet->id]));

    $response->assertOk();
    expect($response->viewData('filters')['department_id'])->toBe($this->service->id)
        ->and($response->viewData('filters')['outlet_id'])->toBe($this->outlet->id);

    $spoils = $response->viewData('spoils');
    expect($spoils->total())->toBe(0);
});

test('spoil report returns nothing for pic_outlet because it has no department assignment', function (): void {
    $pic = spoilUser('PIC_OUTLET', null);

    $response = $this->actingAs($pic)->get(route('laporan.spoil'));
    $response->assertOk();

    expect($response->viewData('spoils')->total())->toBe(0);
});

test('super admin spoil report sees all outlets and departments', function (): void {
    // Catatan: `recorded_date` kolom DATE murni di MySQL (production) --
    // tapi di SQLite (test, dynamically-typed) nilai yang ditulis Eloquent
    // lewat cast 'date' bisa tersimpan sebagai string datetime penuh
    // ('2026-10-07 00:00:00'), bikin perbandingan whereBetween([...,
    // 'YYYY-MM-DD']) berbasis string gagal pas di hari yang sama (string
    // datetime > string date murni secara leksikografis). Ini artefak
    // environment test SQLite, BUKAN bug production (MySQL strict-type
    // kolom DATE otomatis memotong jam-nya) dan BUKAN dari patch ini --
    // jadi testnya pakai date_to besok supaya tidak kena edge-case ini,
    // bukan mengubah kode produksi.
    Storage::fake('local');
    $superAdmin = spoilUser('SUPER_ADMIN', null);
    $barStaff = spoilUser('STAFF_BAR', 'BAR');
    recordSpoilFor($barStaff, $this->outlet, $this->bar, $this->barItem);

    $response = $this->actingAs($superAdmin)->get(route('laporan.spoil', [
        'date_to' => now()->addDay()->toDateString(),
    ]));
    $response->assertOk();

    expect($response->viewData('spoils')->total())->toBeGreaterThan(0);
});

// ===================== cross tenant =====================

test('cross tenant spoil record is not reachable at all', function (): void {
    Storage::fake('local');
    $otherTenant = Tenant::query()->create(['code' => 'OTHERTEN', 'name' => 'Other Tenant', 'status' => 'ACTIVE']);
    $otherBrand = Brand::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTB', 'name' => 'Other Brand', 'status' => 'ACTIVE']);
    $otherLegalEntity = LegalEntity::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTLE', 'name' => 'Other Legal Entity', 'status' => 'ACTIVE']);
    $otherOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'legal_entity_id' => $otherLegalEntity->id,
        'code' => 'OTO1',
        'name' => 'Other Outlet',
        'status' => 'ACTIVE',
    ]);

    $foreignSpoil = SpoilWaste::query()->create([
        'tenant_id' => $otherTenant->id,
        'outlet_id' => $otherOutlet->id,
        'department_id' => null,
        'item_id' => $this->barItem->id,
        'unit_id' => $this->barItem->base_unit_id,
        'qty' => '1',
        'qty_in_base_unit' => '1',
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
        'recorded_at' => now(),
        'status' => SpoilWaste::STATUS_PENDING,
        'approval_status' => SpoilWaste::STATUS_PENDING,
        'created_by' => 1,
    ]);

    $superAdmin = spoilUser('SUPER_ADMIN', null);

    // SUPER_ADMIN lintas departemen/outlet DALAM tenant sendiri -- TAPI
    // TenantScope tetap memblokir lintas TENANT, bahkan untuk superadmin.
    // Ini harus tetap terjaga.
    $this->actingAs($superAdmin)
        ->get(route('operations.spoil-wastes.show', $foreignSpoil))
        ->assertNotFound();
});
