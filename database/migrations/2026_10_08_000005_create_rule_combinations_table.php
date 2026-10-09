<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per exact combination of the four matching dimensions. The unique index guarantees that a
     * combination exists once, and active_rule_version_id (added later) points to at most one active version.
     */
    public function up(): void
    {
        Schema::create('rule_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_segment_id')->constrained('product_segments');
            $table->foreignId('region_id')->constrained('regions');
            $table->foreignId('lead_company_id')->constrained('companies');
            $table->foreignId('sale_type_id')->constrained('sale_types');
            $table->unsignedBigInteger('active_rule_version_id')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['product_segment_id', 'region_id', 'lead_company_id', 'sale_type_id'],
                'rule_combinations_dimensions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_combinations');
    }
};
