<?php

use App\Models\User;
use App\Modules\Core\Models\Department;
use App\Modules\Core\Models\Outlet;
use Database\Seeders\MinimumMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        MinimumMasterDataSeeder::class,
    ]);

    $this->admin = User::query()->where('email', 'admin@sifobi.test')->firstOrFail();
    $this->outlet = Outlet::query()->where('tenant_id', $this->admin->tenant_id)->where('code', 'MKO_OUTLET_1')->firstOrFail();
    $this->bar = Department::query()->where('tenant_id', $this->admin->tenant_id)->where('code', 'BAR')->firstOrFail();
});

/**
 * Bikin file 2-sheet persis format asli (bukan 1 sheet) -- WAJIB 2 sheet
 * supaya WithMultipleSheets benar2 teruji, bukan cuma 1 collection().
 *
 * @param  list<array<string, mixed>>  $newUserRows
 * @param  list<array<string, mixed>>  $deactivateRows
 */
function buildUserImportXlsx(array $newUserRows, array $deactivateRows, string $filename): string
{
    $path = storage_path('framework/testing/'.$filename);
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Import User');
    $sheet1->fromArray(array_merge(
        [['Nama', 'Email', 'Telp', 'Password (Nokom)', 'Role', 'Outlet ID', 'Dept ID', 'Status']],
        array_map(fn ($r) => [$r['nama'], $r['email'], $r['telp'] ?? '', $r['password'], $r['role'], $r['outlet_id'] ?? '', $r['dept_id'] ?? '', $r['status'] ?? 'active'], $newUserRows)
    ));
    // Password kolom WAJIB text, bukan number -- kalau tidak, angka nol
    // di depan hilang sebelum PHP sempat baca (sama persis kasus nyata
    // yang sudah ditemukan di file produksi).
    foreach ($newUserRows as $i => $r) {
        $sheet1->getCellByColumnAndRow(4, $i + 2)->setValueExplicit((string) $r['password'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Nonaktifkan Akun Trial');
    $sheet2->fromArray(array_merge(
        [['ID', 'Email', 'Role', 'Outlet', 'Login terakhir']],
        array_map(fn ($r) => [$r['id'], $r['email'], $r['role'] ?? '', $r['outlet'] ?? '', $r['login_terakhir'] ?? ''], $deactivateRows)
    ));

    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function uploadUserImportFile(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('preview reads BOTH sheets correctly -- proves WithMultipleSheets, not just the first sheet', function (): void {
    $path = buildUserImportXlsx(
        [['nama' => 'Staf Baru', 'email' => 'staf.baru.import@sifobi.test', 'password' => '123456', 'role' => 'STAFF_BAR', 'outlet_id' => $this->outlet->id, 'dept_id' => $this->bar->id]],
        [['id' => $this->admin->id, 'email' => $this->admin->email]],
        'user-import-both-sheets.xlsx'
    );

    $response = $this->actingAs($this->admin)
        ->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);

    $response->assertOk();
    $data = $response->json();

    expect($data['new_users']['total'])->toBe(1)
        ->and($data['new_users']['creatable'])->toBe(1)
        ->and($data['deactivations']['total'])->toBe(1);
});

test('preview flags a same-tenant duplicate email as duplicate_update, with a diff of what changed', function (): void {
    $path = buildUserImportXlsx(
        [['nama' => 'Super Admin Baru', 'email' => $this->admin->email, 'password' => '123456', 'role' => 'STAFF_BAR', 'outlet_id' => $this->outlet->id]],
        [],
        'user-import-dup.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);
    $row = $response->json('new_users.rows.0');

    expect($row['row_status'])->toBe('duplicate_update')
        ->and($row['changes'])->toHaveKeys(['nama', 'role', 'outlet_id'])
        ->and($row['changes']['nama']['from'])->toBe('Super Admin')
        ->and($row['changes']['nama']['to'])->toBe('Super Admin Baru');
});

test('preview flags a same-tenant duplicate with identical data as duplicate_no_change', function (): void {
    $path = buildUserImportXlsx(
        [['nama' => $this->admin->name, 'email' => $this->admin->email, 'password' => '123456', 'role' => 'SUPER_ADMIN', 'outlet_id' => null, 'status' => 'active']],
        [],
        'user-import-no-change.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);
    $row = $response->json('new_users.rows.0');

    expect($row['row_status'])->toBe('duplicate_no_change');
});

test('preview flags a cross-tenant duplicate email as skip_duplicate, never offered for update', function (): void {
    $foreignUser = User::factory()->create(['email' => 'foreign.tenant.import@sifobi.test', 'status' => 'ACTIVE']);

    $path = buildUserImportXlsx(
        [['nama' => 'Mencoba Update Lintas Tenant', 'email' => $foreignUser->email, 'password' => '123456', 'role' => 'STAFF_BAR', 'outlet_id' => $this->outlet->id]],
        [],
        'user-import-cross-tenant.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);
    $row = $response->json('new_users.rows.0');

    expect($row['row_status'])->toBe('skip_duplicate')
        ->and($row)->not->toHaveKey('changes');

    expect($foreignUser->refresh()->name)->not->toBe('Mencoba Update Lintas Tenant');
});

test('apply updates an existing same-tenant user non-password fields, and NEVER touches their password', function (): void {
    $existing = User::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'name' => 'Nama Lama',
        'status' => 'ACTIVE',
        'outlet_id' => null,
    ]);
    $existing->assignRole('STAFF_BAR');
    $originalPasswordHash = $existing->password;

    $path = buildUserImportXlsx(
        [['nama' => 'Nama Sudah Diperbaiki', 'email' => $existing->email, 'password' => '999999', 'role' => 'PIC_OUTLET', 'outlet_id' => $this->outlet->id]],
        [],
        'user-import-update-apply.xlsx'
    );

    $preview = $this->actingAs($this->admin)
        ->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)])
        ->json();

    $newUsersPayload = array_map(fn ($r) => $r + ['_include' => true], $preview['new_users']['rows']);

    $response = $this->actingAs($this->admin)->postJson(route('settings.users.import.apply'), [
        'new_users' => $newUsersPayload,
        'deactivations' => [],
    ]);

    $response->assertOk();
    expect($response->json('new_users.updated'))->toBe(1)
        ->and($response->json('new_users.created'))->toBe(0);

    $existing->refresh();
    expect($existing->name)->toBe('Nama Sudah Diperbaiki')
        ->and($existing->hasRole('PIC_OUTLET'))->toBeTrue()
        ->and($existing->outlet_id)->toBe($this->outlet->id)
        ->and($existing->password)->toBe($originalPasswordHash); // password LAMA tidak tersentuh.
});

test('apply update re-validates server-side -- a tampered row claiming SUPER_ADMIN is rejected unless acting user is SUPER_ADMIN', function (): void {
    $nonSuperAdmin = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'ACTIVE']);
    $nonSuperAdmin->assignRole('PIC_OUTLET');

    $targetUser = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'ACTIVE']);
    $targetUser->assignRole('STAFF_BAR');

    $this->actingAs($nonSuperAdmin)->postJson(route('settings.users.import.apply'), [
        'new_users' => [[
            'nama' => 'Eskalasi Update', 'email' => $targetUser->email, 'password_nokom' => '123456',
            'role' => 'SUPER_ADMIN', 'outlet_id' => null, 'dept_id' => null, 'row_status' => 'duplicate_update',
        ]],
        'deactivations' => [],
    ]);

    expect($targetUser->refresh()->hasRole('SUPER_ADMIN'))->toBeFalse();
});

test('preview rejects an outlet-scoped role with no outlet_id as error', function (): void {
    $path = buildUserImportXlsx(
        [['nama' => 'Tanpa Outlet', 'email' => 'tanpa.outlet.import@sifobi.test', 'password' => '123456', 'role' => 'STAFF_BAR', 'outlet_id' => null]],
        [],
        'user-import-no-outlet.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);
    $row = $response->json('new_users.rows.0');

    expect($row['row_status'])->toBe('error');
});

test('preview flags a short password as a warning without blocking it from being creatable', function (): void {
    $path = buildUserImportXlsx(
        [['nama' => 'Password Pendek', 'email' => 'pw.pendek.import@sifobi.test', 'password' => '154', 'role' => 'STAFF_BAR', 'outlet_id' => $this->outlet->id]],
        [],
        'user-import-short-pw.xlsx'
    );

    $response = $this->actingAs($this->admin)->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);
    $row = $response->json('new_users.rows.0');

    expect($row['row_status'])->toBe('creatable')
        ->and($row['password_short_warning'])->toBeTrue()
        ->and($row['password_nokom'])->toBe('154');
});

test('preview matches deactivation rows by id+email and flags mismatches as not_found', function (): void {
    $otherUser = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'ACTIVE']);

    $path = buildUserImportXlsx([], [
        ['id' => $otherUser->id, 'email' => 'wrong-email@sifobi.test'],
    ], 'user-import-mismatch.xlsx');

    $response = $this->actingAs($this->admin)->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)]);
    $row = $response->json('deactivations.rows.0');

    expect($row['status'])->toBe('not_found');
});

test('apply creates users with a correctly (single) hashed password, assigns role, and deactivates the trial account', function (): void {
    $trialUser = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'ACTIVE', 'email' => 'trial.import@sifobi.test']);

    $path = buildUserImportXlsx(
        [['nama' => 'Staf Baru Apply', 'email' => 'staf.apply.import@sifobi.test', 'password' => '002812', 'role' => 'STAFF_BAR', 'outlet_id' => $this->outlet->id, 'dept_id' => $this->bar->id]],
        [['id' => $trialUser->id, 'email' => $trialUser->email]],
        'user-import-apply.xlsx'
    );

    $preview = $this->actingAs($this->admin)
        ->post(route('settings.users.import.preview'), ['file' => uploadUserImportFile($path)])
        ->json();

    $newUsersPayload = array_map(fn ($r) => $r + ['_include' => true], $preview['new_users']['rows']);
    $deactivationsPayload = array_map(fn ($r) => $r + ['_include' => true], $preview['deactivations']['rows']);

    $response = $this->actingAs($this->admin)->postJson(route('settings.users.import.apply'), [
        'new_users' => $newUsersPayload,
        'deactivations' => $deactivationsPayload,
    ]);

    $response->assertOk();
    $result = $response->json();
    expect($result['new_users']['created'])->toBe(1)
        ->and($result['deactivations']['processed'])->toBe(1);

    $created = User::query()->where('email', 'staf.apply.import@sifobi.test')->firstOrFail();
    expect($created->hasRole('STAFF_BAR'))->toBeTrue()
        ->and($created->outlet_id)->toBe($this->outlet->id)
        ->and(Hash::check('002812', $created->password))->toBeTrue(); // bukti TIDAK double-hashed.

    expect($trialUser->refresh()->status)->toBe('INACTIVE');
});

test('apply re-validates server-side -- a tampered row claiming SUPER_ADMIN is rejected unless acting user is SUPER_ADMIN', function (): void {
    $nonSuperAdmin = User::factory()->create(['tenant_id' => $this->admin->tenant_id, 'status' => 'ACTIVE']);
    $nonSuperAdmin->assignRole('PIC_OUTLET');

    // 'row_status' => 'creatable' WAJIB persis supaya applyChanges() benar2
    // mencoba proses baris ini (bukan ke-skip diam-diam duluan) --
    // membuktikan guard assertMayAssignRole() yang menolak, bukan
    // sekadar baris tidak diproses sama sekali.
    $this->actingAs($nonSuperAdmin)->postJson(route('settings.users.import.apply'), [
        'new_users' => [[
            'nama' => 'Eskalasi', 'email' => 'eskalasi.import@sifobi.test', 'password_nokom' => '123456',
            'role' => 'SUPER_ADMIN', 'outlet_id' => null, 'dept_id' => null, 'row_status' => 'creatable',
        ]],
        'deactivations' => [],
    ]);

    expect(User::query()->where('email', 'eskalasi.import@sifobi.test')->exists())->toBeFalse();
});

test('apply skips own account from the deactivation list', function (): void {
    $response = $this->actingAs($this->admin)->postJson(route('settings.users.import.apply'), [
        'new_users' => [],
        'deactivations' => [[
            'id' => $this->admin->id, 'email' => $this->admin->email, 'status' => 'will_deactivate',
        ]],
    ]);

    $response->assertOk();
    expect($this->admin->refresh()->status)->toBe('ACTIVE');
});
