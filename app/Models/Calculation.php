<?php

namespace App\Models;

use App\Commission\Money\Currency;
use App\Commission\Outcome;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An immutable calculation with its ORIGINAL outcome. A later manual decision is stored separately.
 */
class Calculation extends Model
{
    use HasUuids, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_segment_id', 'region_id', 'lead_company_id', 'sale_type_id',
        'eligible_amount_minor', 'currency', 'external_order_reference', 'plant_reference',
        'outcome', 'rule_version_id', 'rate_bps', 'commission_minor',
        'payer_company_id', 'payee_company_id', 'policy_version',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'currency' => Currency::class,
            'eligible_amount_minor' => 'integer',
            'rate_bps' => 'integer',
            'commission_minor' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payer_company_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payee_company_id');
    }

    public function manualDecision(): HasOne
    {
        return $this->hasOne(ManualDecision::class);
    }

    public function auditRecords(): HasMany
    {
        return $this->hasMany(AuditRecord::class)->orderBy('id');
    }

    public function originalAudit(): HasOne
    {
        return $this->hasOne(AuditRecord::class)->where('event', AuditRecord::EVENT_CALCULATED);
    }

    /** "final" (fixed rule), "decided" (pending, then manually decided) or "pending". */
    public function status(): string
    {
        if (! $this->outcome->isPending()) {
            return 'final';
        }

        return $this->manualDecision ? 'decided' : 'pending';
    }

    public function canReceiveManualDecision(): bool
    {
        return $this->status() === 'pending';
    }
}
