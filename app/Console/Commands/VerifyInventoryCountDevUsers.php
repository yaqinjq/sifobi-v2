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
        // Allow-list (bukan deny-list) -- sama alasannya dgn
        // SeedInventoryCountDevFixtures: isProduction() lolos utk
        // APP_ENV='staging', yang mungkin punya data akun riil.
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refused: this command only runs in local/testing environments (current: '.app()->environment().').');

            return self::FAILURE;
        }

        $count = User::query()
            ->whereIn('email', ['bar_staff@sifobi.test', 'kitchen_staff@sifobi.test', 'admin@sifobi.test'])
            ->update(['email_verified_at' => now()]);

        $this->line("Verified $count user(s).");

        return self::SUCCESS;
    }
}
