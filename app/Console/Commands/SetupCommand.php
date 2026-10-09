<?php

namespace App\Console\Commands;

use App\Models\ProductSegment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Run at every container start. Safe to repeat: migrations are incremental, and sample data is seeded only
 * when the database has never been seeded, so rule changes and manual decisions survive restarts.
 */
class SetupCommand extends Command
{
    protected $signature = 'commission:setup {--wait=90 : Seconds to wait for the database}';

    protected $description = 'Wait for the database, run migrations, and seed sample data on first start only';

    public function handle(): int
    {
        if (! $this->waitForDatabase((int) $this->option('wait'))) {
            $this->error('The database did not become available in time.');

            return self::FAILURE;
        }

        $this->call('migrate', ['--force' => true]);

        if (ProductSegment::query()->exists()) {
            $this->info('Existing data found: sample data not re-seeded.');
        } else {
            $this->info('Empty database: seeding masters and illustrative rules.');
            $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        }

        return self::SUCCESS;
    }

    private function waitForDatabase(int $seconds): bool
    {
        $deadline = time() + $seconds;

        do {
            try {
                DB::connection()->getPdo();

                return true;
            } catch (Throwable) {
                $this->line('Waiting for the database...');
                DB::purge();
                sleep(2);
            }
        } while (time() < $deadline);

        return false;
    }
}
