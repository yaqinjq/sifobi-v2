<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Definisi role & permission standar SIFOBI — dipakai untuk seed MKO
 * (tenant 1, lewat RolesAndPermissionsSeeder) MAUPUN tenant baru saat
 * onboarding (lihat Admin\TenantController::store()). Satu sumber
 * kebenaran supaya tenant baru tidak ketinggalan permission yang sudah
 * jadi standar tapi ditambahkan belakangan lewat migration terpisah
 * (mis. view_audit_log).
 *
 * `manage_tenants` SENGAJA tidak dimasukkan di sini — itu permission
 * khusus pemilik platform (cuma SUPER_ADMIN tenant 1), diberikan lewat
 * migration tersendiri, bukan bagian dari role standar tiap tenant.
 */
class TenantRoleSeeder
{
    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            'view_dashboard',
            'manage_core',
            'manage_settings',
            'manage_brands_outlets',
            'manage_integrations',
            'manage_users',
            'manage_stock_configs',
            'manage_calendar_events',
            'view_master_data',
            'manage_items',
            'manage_units',
            'view_inventory',
            'input_open_stock',
            'post_open_stock',
            'input_opname',
            'approve_opname',
            'input_receiving',
            'approve_receiving',
            'view_goods_receipt',
            'create_goods_receipt',
            'submit_goods_receipt',
            'approve_goods_receipt',
            'reject_goods_receipt',
            'record_spoil',
            'approve_spoil',
            'input_spoil_waste',
            'approve_spoil_waste',
            'create_po',
            'approve_po',
            'view_reports',
            'view_all_reports',
            'view_stock_balance',
            'export_master_data',
            'import_master_data',
            'create_stock_transfers',
            'approve_stock_transfers',
            'view_all_po',
            'manage_recipes',
            'approve_recipes',
            'view_audit_log',
            'operate_pos',
            'manage_pos_layout',
            'view_pos_reports',
            'void_pos_order',
            'approve_pos_shift',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        $permissions = self::permissions();

        return [
            'SUPER_ADMIN' => $permissions,
            'ADMIN'       => $permissions,
            'GENERAL_FINANCE' => [
                'view_all_po',
                'view_dashboard',
                'manage_settings',
                'manage_stock_configs',
                'view_master_data',
                'manage_items',
                'manage_units',
                'view_inventory',
                'approve_receiving',
                'view_goods_receipt',
                'approve_goods_receipt',
                'approve_po',
                'view_reports',
                'view_all_reports',
                'view_stock_balance',
                'export_master_data',
                'import_master_data',
                'approve_stock_transfers',
                'manage_recipes',
                'approve_recipes',
                'view_audit_log',
                'manage_pos_layout',
                'view_pos_reports',
                'void_pos_order',
                'approve_pos_shift',
            ],
            'FINANCE_STAFF' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_goods_receipt',
                'view_reports',
                'view_stock_balance',
                'export_master_data',
            ],
            'FINANCE_ACCOUNTING_STAFF' => [
                'view_dashboard',
                'view_master_data',
                'manage_items',
                'view_inventory',
                'view_goods_receipt',
                'view_reports',
                'view_stock_balance',
                'export_master_data',
            ],
            'MANAGER_AREA' => [
                'view_all_po',
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_goods_receipt',
                'create_goods_receipt',
                'submit_goods_receipt',
                'approve_goods_receipt',
                'reject_goods_receipt',
                'approve_opname',
                'record_spoil',
                'approve_spoil',
                'approve_spoil_waste',
                'manage_calendar_events',
                'view_reports',
                'view_all_reports',
                'view_stock_balance',
                'export_master_data',
                'approve_stock_transfers',
                'manage_recipes',
                'approve_recipes',
                'view_audit_log',
                'manage_pos_layout',
                'view_pos_reports',
                'void_pos_order',
                'approve_pos_shift',
            ],
            'PIC_OUTLET' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'input_open_stock',
                'post_open_stock',
                'input_opname',
                'approve_opname',
                'input_receiving',
                'approve_receiving',
                'view_goods_receipt',
                'create_goods_receipt',
                'submit_goods_receipt',
                'record_spoil',
                'approve_spoil',
                'input_spoil_waste',
                'approve_spoil_waste',
                'create_po',
                'approve_po',
                'view_reports',
                'view_stock_balance',
                'export_master_data',
                'manage_recipes',
                'approve_recipes',
                'create_stock_transfers',
                'approve_stock_transfers',
                'operate_pos',
                'manage_pos_layout',
                'view_pos_reports',
                'void_pos_order',
                'approve_pos_shift',
            ],
            'STAFF_BAR' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_stock_balance',
                'view_goods_receipt',
                'create_goods_receipt',
                'submit_goods_receipt',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'create_po',
                'operate_pos',
            ],
            'STAFF_KITCHEN' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_stock_balance',
                'view_goods_receipt',
                'create_goods_receipt',
                'submit_goods_receipt',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'create_po',
                'operate_pos',
            ],
            'STAFF_SERVICE' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_goods_receipt',
                'view_reports',
                'view_stock_balance',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'export_master_data',
                'operate_pos',
            ],
            'STAFF_GUDANG' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_goods_receipt',
                'view_reports',
                'view_stock_balance',
                'create_goods_receipt',
                'submit_goods_receipt',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'create_po',
                'export_master_data',
                'create_stock_transfers',
            ],
        ];
    }

    public function seedForTenant(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // Permission adalah kosakata global (tabel permissions tidak
        // team-scoped) — aman firstOrCreate tanpa team context.
        foreach (self::permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $registrar->setPermissionsTeamId($tenantId);

        foreach (self::rolePermissions() as $roleName => $rolePermissionNames) {
            $role = Role::firstOrCreate([
                'team_id' => $tenantId,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissionNames);
        }

        // manage_tenants sengaja tidak ada di rolePermissions() (lihat docblock
        // class ini) tapi syncPermissions() di atas tetap menghapusnya dari
        // SUPER_ADMIN tenant 1 kalau sudah pernah diberikan lewat migration
        // terpisah — jadi harus di-restore lagi di sini, bukan cuma diingatkan
        // lewat komentar (sudah kejadian ke-strip 2x gara-gara itu).
        if ($tenantId === 1) {
            $superAdmin = Role::firstOrCreate([
                'team_id' => 1,
                'name' => 'SUPER_ADMIN',
                'guard_name' => 'web',
            ]);
            $manageTenants = Permission::firstOrCreate(['name' => 'manage_tenants', 'guard_name' => 'web']);
            $superAdmin->givePermissionTo($manageTenants);
        }

        $registrar->forgetCachedPermissions();
    }
}
