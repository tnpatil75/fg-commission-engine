<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * At most one final manual decision per calculation (unique calculation_id), even under concurrent submissions.
     */
    public function up(): void
    {
        Schema::create('manual_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('calculation_id')->unique()->constrained('calculations');
            $table->unsignedSmallInteger('rate_bps');
            $table->unsignedBigInteger('commission_minor');
            $table->char('currency', 3);
            $table->foreignId('payer_company_id')->constrained('companies');
            $table->foreignId('payee_company_id')->constrained('companies');
            $table->string('approver_name', 100);
            $table->text('reason');
            $table->string('policy_version', 40);
            $table->timestamp('decided_at')->useCurrent();
        });

        DB::statement('ALTER TABLE manual_decisions ADD CONSTRAINT manual_decisions_check CHECK (
            rate_bps <= 10000 AND payer_company_id <> payee_company_id
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_decisions');
    }
};
