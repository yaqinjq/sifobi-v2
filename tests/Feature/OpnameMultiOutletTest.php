<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
use App\Modules\Operations\Models\OpnameSession;
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
    $this->outlet1 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->outlet2 = Outlet::query()->where('tenant_id', $this->tenant->id)->where('code', 'MKO_OUTLET_2')->firstOrFail();
    $this->department = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'BAR')->firstOrFail();
});

test('a single-outlet user can still create an opname session with zero UI change', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->department->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    $this->actingAs($user)
        ->get(route('operations.opname.create'))
        ->assertOk()
        ->assertViewHas('canChangeOutlet', false);

    $this->actingAs($user)
        ->post(route('operations.opname.store'), ['shift' => 'PAGI'])
        ->assertRedirect();

    $session = OpnameSession::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect((int) $session->outlet_id)->toBe($this->outlet1->id);
});

test('a multi-outlet user sees an outlet picker and must choose one of their own outlets', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->department->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $response = $this->actingAs($user)->get(route('operations.opname.create'));
    $response->assertOk()->assertViewHas('canChangeOutlet', true);

    $outlets = $response->viewData('outlets')->pluck('id')->sort()->values()->all();
    expect($outlets)->toBe(collect([$this->outlet1->id, $this->outlet2->id])->sort()->values()->all());

    // Tanpa outlet_id -- ditolak.
    $this->actingAs($user)
        ->post(route('operations.opname.store'), ['shift' => 'PAGI'])
        ->assertSessionHasErrors('outlet_id');

    // Outlet_id yang sah (salah satu miliknya) -- berhasil.
    $this->actingAs($user)
        ->post(route('operations.opname.store'), ['shift' => 'PAGI', 'outlet_id' => $this->outlet2->id])
        ->assertRedirect();

    $session = OpnameSession::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect((int) $session->outlet_id)->toBe($this->outlet2->id);
});

test('a multi-outlet user cannot create a session for an outlet outside their own list -- authorization test', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->department->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');

    $thirdOutlet = Outlet::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->outlet1->brand_id,
        'legal_entity_id' => $this->outlet1->legal_entity_id,
        'code' => 'MKO_OUTLET_3_'.uniqid(),
        'name' => 'Outlet Ketiga',
        'status' => 'ACTIVE',
    ]);
    $user->outlets()->sync([$this->outlet1->id, $thirdOutlet->id]);

    $this->actingAs($user)
        ->post(route('operations.opname.store'), ['shift' => 'PAGI', 'outlet_id' => $this->outlet2->id])
        ->assertSessionHasErrors('outlet_id');

    expect(OpnameSession::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('a multi-outlet user can view and act on a session from any of their own outlets, not just the first', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->department->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $this->actingAs($user)
        ->post(route('operations.opname.store'), ['shift' => 'PAGI', 'outlet_id' => $this->outlet2->id])
        ->assertRedirect();

    $session = OpnameSession::query()->where('outlet_id', $this->outlet2->id)->firstOrFail();

    // Sebelum perbaikan ini, authorizeSessionOwnership() akan menolak
    // akses ke sesi outlet2 karena cuma mengecek outlet_id LEGACY
    // (outlet1) -- sekarang harus berhasil karena outlet2 ada di
    // daftar accessibleOutletIds() milik user ini.
    $this->actingAs($user)
        ->get(route('operations.opname.show', $session))
        ->assertOk();
});

test('opname index lists sessions across all of a multi-outlet users accessible outlets', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'department_id' => $this->department->id,
        'status' => 'ACTIVE',
    ]);
    $user->assignRole('PIC_OUTLET');
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $this->actingAs($user)->post(route('operations.opname.store'), ['shift' => 'PAGI', 'outlet_id' => $this->outlet1->id])->assertRedirect();
    $this->actingAs($user)->post(route('operations.opname.store'), ['shift' => 'SORE', 'outlet_id' => $this->outlet2->id])->assertRedirect();

    expect(OpnameSession::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2);

    $response = $this->actingAs($user)->get(route('operations.opname.index'));
    $response->assertOk();

    $sessions = $response->viewData('sessions');
    expect($sessions->total())->toBe(2);
});
