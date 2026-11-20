<?php

use App\Models\User;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
});

// <input type="month"> tidak didukung Firefox/Safari desktop (jadi kotak
// teks biasa) -- kedua halaman import bulanan harus pakai dropdown Bulan +
// Tahun yang jalan di semua browser.
test('halaman import bulanan pakai dropdown bulan & tahun, bukan input type=month', function (string $routeName): void {
    $this->actingAs($this->admin)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Pilih bulan')
        ->assertSee('November')
        ->assertDontSee('type="month"', false);
})->with([
    'spoil bulanan' => 'operations.spoil-wastes.import-bulanan.form',
    'opname bulanan' => 'operations.opname.import-bulanan.form',
]);
