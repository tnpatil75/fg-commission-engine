<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable calculation results. Pending outcomes (case by case, no match) must have no final
     * rate, amount, payer or payee; this is enforced by a CHECK constraint, not only in PHP.
     */
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_segment_id')->constrained('product_segments');
            $table->foreignId('region_id')->constrained('regions');
            $table->foreignId('lead_company_id')->constrained('companies');
            $table->foreignId('sale_type_id')->constrained('sale_types');
            $table->unsignedBigInteger('eligible_amount_minor');
            $table->char('currency', 3);
            // External references are plain inputs, not foreign keys into an order, production or any other system.
            $table->string('external_order_reference', 100)->nullable()->index();
            $table->string('plant_reference', 50)->nullable();
            $table->string('outcome', 20)->index();
            $table->foreignId('rule_version_id')->nullable()->constrained('rule_versions');
            $table->unsignedSmallInteger('rate_bps')->nullable();
            $table->unsignedBigInteger('commission_minor')->nullable();
            $table->foreignId('payer_company_id')->nullable()->constrained('companies');
            $table->foreignId('payee_company_id')->nullable()->constrained('companies');
            $table->string('policy_version', 40);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::statement("ALTER TABLE calculations ADD CONSTRAINT calculations_amount_check CHECK (
            eligible_amount_minor > 0 AND currency IN ('AED', 'USD', 'EUR', 'INR')
        )");

        DB::statement("ALTER TABLE calculations ADD CONSTRAINT calculations_outcome_check CHECK (
            (outcome = 'fixed' AND rule_version_id IS NOT NULL AND rate_bps IS NOT NULL AND rate_bps <= 10000
                AND commission_minor IS NOT NULL AND payer_company_id IS NOT NULL AND payee_company_id IS NOT NULL
                AND payer_company_id <> payee_company_id)
            OR
            (outcome = 'case_by_case' AND rule_version_id IS NOT NULL AND rate_bps IS NULL
                AND commission_minor IS NULL AND payer_company_id IS NULL AND payee_company_id IS NULL)
            OR
            (outcome = 'no_match' AND rule_version_id IS NULL AND rate_bps IS NULL
                AND commission_minor IS NULL AND payer_company_id IS NULL AND payee_company_id IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};
