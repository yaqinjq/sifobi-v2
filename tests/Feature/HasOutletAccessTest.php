<?php

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\Tenant;
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
});

test('legacy single-outlet user resolves via the outlet_id column', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);

    expect($user->accessibleOutletIds())->toBe([$this->outlet1->id])
        ->and($user->hasSingleOutlet())->toBeTrue()
        ->and($user->hasMultipleOutlets())->toBeFalse()
        ->and($user->soleOutletId())->toBe($this->outlet1->id);
});

test('global-access user with no outlet_id and no pivot rows resolves to empty', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => null,
        'status' => 'ACTIVE',
    ]);

    expect($user->accessibleOutletIds())->toBe([])
        ->and($user->hasSingleOutlet())->toBeFalse()
        ->and($user->hasMultipleOutlets())->toBeFalse()
        ->and($user->soleOutletId())->toBeNull();
});

test('multi-outlet user via pivot resolves to the pivot list, not the legacy column', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);
    $user->outlets()->sync([$this->outlet1->id, $this->outlet2->id]);

    $ids = $user->accessibleOutletIds();

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($this->outlet1->id)
        ->and($ids)->toContain($this->outlet2->id)
        ->and($user->hasSingleOutlet())->toBeFalse()
        ->and($user->hasMultipleOutlets())->toBeTrue()
        ->and($user->soleOutletId())->toBeNull();
});

test('pivot with exactly one row still resolves as a single outlet', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => null,
        'status' => 'ACTIVE',
    ]);
    $user->outlets()->sync([$this->outlet2->id]);

    expect($user->accessibleOutletIds())->toBe([$this->outlet2->id])
        ->and($user->hasSingleOutlet())->toBeTrue()
        ->and($user->soleOutletId())->toBe($this->outlet2->id);
});

test('backfill migration gave every pre-existing single-outlet user an equivalent user_outlets row', function (): void {
    // RefreshDatabase menjalankan SEMUA migration (termasuk backfill)
    // di awal, SEBELUM user manapun dibuat -- jadi tidak mungkin
    // menguji migration backfill-nya sendiri lewat replay migration
    // dalam 1 siklus RefreshDatabase (user yang dibuat test ini belum
    // ada saat migration jalan). Jadi diuji langsung LOGIKA yang
    // persis sama dengan isi migration backfill (lihat
    // 2026_10_23_100002_backfill_user_outlets_from_legacy_outlet_id.php),
    // dijalankan manual di sini setelah user legacy dibuat.
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'outlet_id' => $this->outlet1->id,
        'status' => 'ACTIVE',
    ]);

    expect($user->outlets()->count())->toBe(0);

    \Illuminate\Support\Facades\DB::table('users')->whereNotNull('outlet_id')->orderBy('id')->chunkById(500, function ($users): void {
        $rows = $users->map(fn ($u) => [
            'user_id' => $u->id,
            'outlet_id' => $u->outlet_id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            \Illuminate\Support\Facades\DB::table('user_outlets')->insertOrIgnore($rows);
        }
    });

    expect($user->outlets()->count())->toBe(1)
        ->and($user->fresh()->outlets()->first()->id)->toBe($this->outlet1->id);
});
