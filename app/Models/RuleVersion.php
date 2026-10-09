<?php

namespace App\Models;

use App\Commission\Money\MoneyCalculator;
use App\Commission\RuleKind;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable version of a rule. Changing a rule means inserting a new version.
 */
class RuleVersion extends Model
{
    use HasUuids, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'rule_combination_id', 'version', 'kind', 'rate_bps', 'payer_company_id', 'payee_company_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RuleKind::class,
            'version' => 'integer',
            'rate_bps' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function combination(): BelongsTo
    {
        return $this->belongsTo(RuleCombination::class, 'rule_combination_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payer_company_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payee_company_id');
    }

    public function reference(): string
    {
        return "Rule #{$this->rule_combination_id} v{$this->version}";
    }

    public function summary(): string
    {
        if ($this->kind === RuleKind::CaseByCase) {
            return 'Decided case by case';
        }

        return MoneyCalculator::rateToPercent($this->rate_bps)."%, {$this->payer->name} pays {$this->payee->name}";
    }

    /**
     * Values for the update form. Empty strings leave the fixed-rule fields blank for a case-by-case version.
     *
     * @return array{kind: string, rate: string, payer: string, payee: string, note: string}
     */
    public function formValues(): array
    {
        return [
            'kind' => $this->kind->value,
            'rate' => $this->rate_bps === null ? '' : MoneyCalculator::rateToPercent($this->rate_bps),
            'payer' => $this->payer?->code ?? '',
            'payee' => $this->payee?->code ?? '',
            'note' => $this->note ?? '',
        ];
    }

    /**
     * Everything needed to understand the rule later, with labels as they are now.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'rule_id' => $this->rule_combination_id,
            'rule_version_id' => $this->id,
            'rule_version_uuid' => $this->uuid,
            'version' => $this->version,
            'reference' => $this->reference(),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'rate_bps' => $this->rate_bps,
            'rate_percent' => $this->rate_bps === null ? null : MoneyCalculator::rateToPercent($this->rate_bps),
            'payer' => $this->payer?->snapshot(),
            'payee' => $this->payee?->snapshot(),
            'dimensions' => $this->combination->dimensionsSnapshot(),
            'note' => $this->note,
            'version_created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
