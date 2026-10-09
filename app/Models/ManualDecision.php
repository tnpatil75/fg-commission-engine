<?php

namespace App\Models;

use App\Commission\Money\Currency;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single final manual decision for a pending calculation. The approver name is self-declared in this demo.
 */
class ManualDecision extends Model
{
    use HasUuids, Immutable;

    public const CREATED_AT = 'decided_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'calculation_id', 'rate_bps', 'commission_minor', 'currency',
        'payer_company_id', 'payee_company_id', 'approver_name', 'reason', 'policy_version',
    ];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'rate_bps' => 'integer',
            'commission_minor' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payer_company_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payee_company_id');
    }
}
