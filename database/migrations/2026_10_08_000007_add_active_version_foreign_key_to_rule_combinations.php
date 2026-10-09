<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The active pointer may only reference a version of the same combination. A NULL pointer means the
     * rule is inactive (MySQL skips composite foreign key checks when any column is NULL).
     */
    public function up(): void
    {
        Schema::table('rule_combinations', function (Blueprint $table) {
            $table->foreign(['active_rule_version_id', 'id'], 'rule_combinations_active_version_fk')
                ->references(['id', 'rule_combination_id'])
                ->on('rule_versions');
        });
    }

    public function down(): void
    {
        Schema::table('rule_combinations', function (Blueprint $table) {
            $table->dropForeign('rule_combinations_active_version_fk');
        });
    }
};
