<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable rule versions. A change to a rule always inserts a new version; old versions stay
     * available for history and for the calculations that reference them.
     */
    public function up(): void
    {
        Schema::create('rule_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('rule_combination_id')->constrained('rule_combinations');
            $table->unsignedInteger('version');
            $table->string('kind', 20);
            $table->unsignedSmallInteger('rate_bps')->nullable();
            $table->foreignId('payer_company_id')->nullable()->constrained('companies');
            $table->foreignId('payee_company_id')->nullable()->constrained('companies');
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['rule_combination_id', 'version']);
            // Target of the composite foreign key from rule_combinations (version must belong to its combination).
            $table->unique(['id', 'rule_combination_id']);
        });

        DB::statement("ALTER TABLE rule_versions ADD CONSTRAINT rule_versions_kind_check CHECK (
            (kind = 'fixed' AND rate_bps IS NOT NULL AND rate_bps <= 10000
                AND payer_company_id IS NOT NULL AND payee_company_id IS NOT NULL
                AND payer_company_id <> payee_company_id)
            OR
            (kind = 'case_by_case' AND rate_bps IS NULL AND payer_company_id IS NULL AND payee_company_id IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_versions');
    }
};
