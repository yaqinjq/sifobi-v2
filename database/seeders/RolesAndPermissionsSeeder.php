<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $rolePermissions = [
            'SUPER_ADMIN' => $permissions,
            'ADMIN'       => $permissions,
            'GENERAL_FINANCE' => [
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
                'create_stock_transfers',
                'approve_stock_transfers',
            ],
            'STAFF_BAR' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_stock_balance',
                'input_open_stock',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'create_po',
            ],
            'STAFF_KITCHEN' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_stock_balance',
                'input_open_stock',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'create_po',
            ],
            'STAFF_SERVICE' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_goods_receipt',
                'view_reports',
                'view_stock_balance',
                'input_open_stock',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'export_master_data',
            ],
            'STAFF_GUDANG' => [
                'view_dashboard',
                'view_master_data',
                'view_inventory',
                'view_goods_receipt',
                'view_reports',
                'view_stock_balance',
                'create_goods_receipt',
                'input_open_stock',
                'input_opname',
                'record_spoil',
                'input_receiving',
                'input_spoil_waste',
                'create_po',
                'export_master_data',
                'create_stock_transfers',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
