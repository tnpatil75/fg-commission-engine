<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Masters and illustrative rules, in one transaction so a failed first start leaves no partial sample data.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([MasterDataSeeder::class, IllustrativeRuleSeeder::class]);
        });
    }
}
