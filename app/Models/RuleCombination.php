<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One exact combination of segment, region, lead company and sale type.
 * It points to its single active rule version, or to none when the rule is inactive.
 */
class RuleCombination extends Model
{
    protected $fillable = [
        'product_segment_id', 'region_id', 'lead_company_id', 'sale_type_id',
        'active_rule_version_id', 'deactivated_at',
    ];

    protected function casts(): array
    {
        return ['deactivated_at' => 'immutable_datetime'];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(ProductSegment::class, 'product_segment_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function leadCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'lead_company_id');
    }

    public function saleType(): BelongsTo
    {
        return $this->belongsTo(SaleType::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'active_rule_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RuleVersion::class)->orderByDesc('version');
    }

    public function isActive(): bool
    {
        return $this->active_rule_version_id !== null;
    }

    public function label(): string
    {
        return implode(' / ', [
            $this->segment->name, $this->region->name, $this->leadCompany->name, $this->saleType->name,
        ]);
    }

    /** @return array<string, array{code: string, name: string}> */
    public function dimensionsSnapshot(): array
    {
        return [
            'segment' => $this->segment->snapshot(),
            'region' => $this->region->snapshot(),
            'lead_company' => $this->leadCompany->snapshot(),
            'sale_type' => $this->saleType->snapshot(),
        ];
    }
}
