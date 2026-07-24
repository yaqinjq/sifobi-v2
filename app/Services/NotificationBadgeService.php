<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NotificationBadgeService
{
    public static function getBadges(int $tenantId): array
    {
        $badges = [];

        $tables = [
            'open_stock_pending'  => ['table' => 'open_stocks',    'status' => 'DRAFT'],
            'opname_pending'      => ['table' => 'opname_sessions', 'status' => 'SUBMITTED'],
            'spoil_pending'       => ['table' => 'spoil_wastes',    'status' => 'PENDING'],
            'receiving_pending'   => ['table' => 'goods_receipts',  'status' => 'SUBMITTED'],
            'transfer_pending'    => ['table' => 'stock_transfers',  'status' => 'SUBMITTED'],
        ];

        foreach ($tables as $key => $config) {
            try {
                $count = DB::table($config['table'])
                    ->where('tenant_id', $tenantId)
                    ->where('status', $config['status'])
                    ->count();
                $badges[$key] = $count > 0 ? $count : null;
            } catch (\Throwable) {
                $badges[$key] = null;
            }
        }

        return $badges;
    }
}
