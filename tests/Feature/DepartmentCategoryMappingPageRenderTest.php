<?php
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\MinimumMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('department category mapping import page renders correctly', function () {
    $this->seed([RolesAndPermissionsSeeder::class, MinimumMasterDataSeeder::class]);
    $admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('settings.department-category-mapping.import-form'))
        ->assertOk()
        ->assertSee('MENAMBAH/MEMPERBARUI');
});
