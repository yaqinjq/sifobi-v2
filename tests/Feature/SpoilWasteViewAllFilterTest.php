<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Inventory\Models\Item;
use App\Modules\Operations\Models\SpoilWaste;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->outlet1 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->outlet2 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->bar = Department::query()->where('code', 'BAR')->firstOrFail();
    $this->kitchen = Department::query()->where('code', 'KITCHEN')->firstOrFail();
    $this->item = Item::query()->where('canonical_sku', 'MKO-AJINOMOTO-500GR')->firstOrFail();

    $this->spoilOutlet1Bar = viewAllSpoilRecord($this, $this->outlet1, $this->bar);
    $this->spoilOutlet1Kitchen = viewAllSpoilRecord($this, $this->outlet1, $this->kitchen);
    $this->spoilOutlet2Bar = viewAllSpoilRecord($this, $this->outlet2, $this->bar);
});

function viewAllSpoilRecord(object $test, Outlet $outlet, Department $department): SpoilWaste
{
    return SpoilWaste::query()->create([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $outlet->id,
        'department_id' => $department->id,
        'item_id' => $test->item->id,
        'unit_id' => $test->item->base_unit_id,
        'qty' => '1',
        'qty_in_base_unit' => '1',
        'reason_category' => SpoilWaste::REASON_RUSAK,
        'recorded_date' => now()->toDateString(),
        'recorded_at' => now(),
        'status' => SpoilWaste::STATUS_PENDING,
        'approval_status' => SpoilWaste::STATUS_PENDING,
        'created_by' => $test->admin->id,
    ]);
}

function viewAllSpoilUser(object $test, string $role, ?Outlet $outlet = null, ?Department $department = null): User
{
    $user = User::factory()->create([
        'tenant_id' => $test->tenant->id,
        'outlet_id' => $outlet?->id,
        'department_id' => $department?->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole($role);

    return $user;
}

function visibleSpoilIds(object $test, User $user, array $query = []): array
{
    return $test->actingAs($user)
        ->get(route('operations.spoil-wastes.index', $query))
        ->assertOk()
        ->viewData('spoilWastes')
        ->getCollection()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
}

test('Manager Area tanpa outlet tertentu melihat spoil semua outlet & departemen, dan bisa memfilter', function (): void {
    $manager = viewAllSpoilUser($this, 'MANAGER_AREA');

    $all = [$this->spoilOutlet1Bar->id, $this->spoilOutlet1Kitchen->id, $this->spoilOutlet2Bar->id];
    sort($all);

    expect(visibleSpoilIds($this, $manager))->toBe($all)
        ->and(visibleSpoilIds($this, $manager, ['outlet_id' => $this->outlet2->id]))->toBe([$this->spoilOutlet2Bar->id])
        ->and(visibleSpoilIds($this, $manager, ['department_id' => $this->kitchen->id]))->toBe([$this->spoilOutlet1Kitchen->id]);

    $this->actingAs($manager)
        ->get(route('operations.spoil-wastes.index'))
        ->assertSee('Semua departemen')
        ->assertSee('Semua outlet');
});

test('Manager Area yang ditugaskan ke 1 outlet cuma melihat outlet itu (semua departemen)', function (): void {
    $manager = viewAllSpoilUser($this, 'MANAGER_AREA', $this->outlet1);

    $expected = [$this->spoilOutlet1Bar->id, $this->spoilOutlet1Kitchen->id];
    sort($expected);

    expect(visibleSpoilIds($this, $manager))->toBe($expected);

    $this->actingAs($manager)->get(route('operations.spoil-wastes.show', $this->spoilOutlet1Kitchen))->assertOk();
    $this->actingAs($manager)->get(route('operations.spoil-wastes.show', $this->spoilOutlet2Bar))->assertForbidden();
});

test('staf tetap terkunci ke outlet & departemennya sendiri, tanpa filter, walau kirim outlet_id manual', function (): void {
    $staff = viewAllSpoilUser($this, 'STAFF_BAR', $this->outlet1, $this->bar);

    expect(visibleSpoilIds($this, $staff))->toBe([$this->spoilOutlet1Bar->id])
        ->and(visibleSpoilIds($this, $staff, ['outlet_id' => $this->outlet2->id]))->toBe([$this->spoilOutlet1Bar->id]);

    $this->actingAs($staff)
        ->get(route('operations.spoil-wastes.index'))
        ->assertDontSee('Semua departemen');
});

test('General Finance (tanpa izin catat spoil) bisa membuka halaman Spoil & Waste untuk melihat', function (): void {
    $finance = viewAllSpoilUser($this, 'GENERAL_FINANCE');

    expect(visibleSpoilIds($this, $finance))->toHaveCount(3);
});

test('izin lihat semua TIDAK ikut melebarkan approve -- Manager Area tetap ditolak approve di luar scope-nya', function (): void {
    $manager = viewAllSpoilUser($this, 'MANAGER_AREA', $this->outlet1);

    $this->actingAs($manager)
        ->post(route('operations.spoil-wastes.approve', $this->spoilOutlet1Kitchen), ['approval_notes' => 'ok'])
        ->assertForbidden();

    expect($this->spoilOutlet1Kitchen->fresh()->status)->toBe(SpoilWaste::STATUS_PENDING);
});

test('migration memberi izin lihat semua ke role custom "Asisten Super Admin" buatan user', function (): void {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    $custom = Role::create(['name' => 'ASISTEN SUPER ADMIN', 'guard_name' => 'web', 'team_id' => $this->tenant->id]);
    $unrelated = Role::create(['name' => 'KASIR TAMBAHAN', 'guard_name' => 'web', 'team_id' => $this->tenant->id]);

    (require database_path('migrations/2026_11_20_120000_add_view_all_spoil_permission.php'))->up();

    expect($custom->fresh()->hasPermissionTo('view_all_spoil'))->toBeTrue()
        ->and($unrelated->fresh()->hasPermissionTo('view_all_spoil'))->toBeFalse();
});
