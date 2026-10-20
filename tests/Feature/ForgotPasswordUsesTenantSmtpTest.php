<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Modules\Core\Models\Tenant;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->tenant = Tenant::query()->where('code', 'MKO')->firstOrFail();
});

test('forgot password applies the user\'s tenant SMTP settings before sending the reset link', function (): void {
    Notification::fake();

    AppSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update([
        'smtp_host' => 'smtp.tenant-mko.test',
        'smtp_port' => 2525,
        'smtp_username' => 'tenant-user',
        'smtp_password' => 'tenant-secret',
        'smtp_encryption' => 'tls',
        'smtp_from_address' => 'noreply@tenant-mko.test',
        'smtp_from_name' => 'MKO Group',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'forgot-password-user@sifobi.test',
        'status' => 'ACTIVE',
    ]);

    // Request ini cuma redirect (tidak merender view apa pun), jadi
    // View::composer('*') di AppServiceProvider yang biasanya menerapkan
    // SMTP tenant TIDAK PERNAH jalan di sini -- harus diterapkan eksplisit
    // di controller, sama seperti yang dilakukan WorkflowNotification.
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(config('mail.default'))->toBe('smtp');
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.tenant-mko.test');
    expect(config('mail.mailers.smtp.port'))->toBe(2525);
    expect(config('mail.from.address'))->toBe('noreply@tenant-mko.test');
});

test('forgot password for an unknown email does not blow up and still redirects back', function (): void {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody-at-all@sifobi.test'])
        ->assertRedirect();
});
