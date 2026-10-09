<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class VerifyInventoryCountDevUsers extends Command
{
    protected $signature = 'inventory-count:verify-dev-users';

    protected $description = 'Dev-only: mark the seeded bar_staff/kitchen_staff test users as email-verified';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refused: dev-only.');

            return self::FAILURE;
        }

        $count = User::query()
            ->whereIn('email', ['bar_staff@sifobi.test', 'kitchen_staff@sifobi.test'])
            ->update(['email_verified_at' => now()]);

        $this->line("Verified $count user(s).");

        return self::SUCCESS;
    }
}
