<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_tests_run_against_the_separate_test_database(): void
    {
        $this->assertSame(self::TEST_DATABASE, DB::connection()->getDatabaseName());
        $this->assertSame(self::TEST_DATABASE, DB::selectOne('SELECT DATABASE() AS name')->name);
    }

    public function test_the_test_user_cannot_see_the_demo_database(): void
    {
        $visible = array_map(fn ($row) => $row->Database, DB::select('SHOW DATABASES'));

        $this->assertContains(self::TEST_DATABASE, $visible);
        $this->assertNotContains('commission', $visible);
    }
}
