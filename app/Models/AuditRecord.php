<?php

namespace App\Models;

use App\Commission\Outcome;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit entry: what was calculated or decided, with which inputs and rule, and why.
 */
class AuditRecord extends Model
{
    use HasUuids, Immutable;

    public const EVENT_CALCULATED = 'calculated';

    public const EVENT_MANUAL_DECISION = 'manual_decision';

    public const UPDATED_AT = null;

    protected $fillable = [
        'calculation_id', 'manual_decision_id', 'event', 'outcome', 'explanation',
        'input_snapshot', 'rule_snapshot', 'result_snapshot', 'policy_version',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
            'input_snapshot' => 'array',
            'rule_snapshot' => 'array',
            'result_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
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

    public function manualDecision(): BelongsTo
    {
        return $this->belongsTo(ManualDecision::class);
    }

    public function eventLabel(): string
    {
        return $this->event === self::EVENT_CALCULATED ? 'Original calculation' : 'Manual decision';
    }
}
