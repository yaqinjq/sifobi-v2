<?php
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\MinimumMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tutorial and changelog pages render without blade errors', function () {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($admin)->get(route('tutorial'))->assertOk()
        ->assertSee('Mapping Departemen')
        ->assertSee('Aktifkan untuk Opname')
        ->assertSee('Nonaktifkan dari Opname')
        ->assertSee('Kategori Beda per Departemen');
    $this->actingAs($admin)->get(route('changelog'))->assertOk()->assertSee('v2.20');
});
