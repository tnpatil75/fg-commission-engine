<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail. Snapshots keep codes and labels as they were at the time, so records stay
     * understandable after master labels or rules change.
     */
    public function up(): void
    {
        Schema::create('audit_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('calculation_id')->constrained('calculations');
            $table->foreignId('manual_decision_id')->nullable()->unique()->constrained('manual_decisions');
            $table->string('event', 30);
            $table->string('outcome', 20);
            $table->text('explanation');
            $table->json('input_snapshot');
            $table->json('rule_snapshot')->nullable();
            $table->json('result_snapshot');
            $table->string('policy_version', 40);
            $table->timestamp('created_at')->useCurrent();

            // One "calculated" record and at most one "manual_decision" record per calculation.
            $table->unique(['calculation_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_records');
    }
};
