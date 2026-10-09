<?php

namespace App\Http\Resources;

use App\Commission\Data\CalculationResult;
use App\Commission\Money\MoneyCalculator;
use App\Models\AuditRecord;
use App\Models\Calculation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Calculation audit detail: original inputs and outcome, applied rule snapshot, any later manual decision,
 * and the full audit trail.
 *
 * @property Calculation $resource
 */
class CalculationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $calculation = $this->resource;
        $original = $calculation->originalAudit;
        $decision = $calculation->manualDecision;

        return [
            'calculation_id' => $calculation->uuid,
            'status' => $calculation->status(),
            'inputs' => $original->input_snapshot,
            'original_outcome' => CalculationResult::fromModel($calculation)->toArray(),
            'applied_rule' => $original->rule_snapshot,
            'manual_decision' => $decision === null ? null : [
                'decision_id' => $decision->uuid,
                'decided_at' => $decision->decided_at->toIso8601String(),
                'rate_percent' => MoneyCalculator::rateToPercent($decision->rate_bps),
                'rate_bps' => $decision->rate_bps,
                'commission_amount' => MoneyCalculator::toDecimalString($decision->commission_minor, $decision->currency),
                'commission_minor' => $decision->commission_minor,
                'currency' => $decision->currency->value,
                'payer' => $decision->payer->snapshot(),
                'payee' => $decision->payee->snapshot(),
                'approver_name' => $decision->approver_name,
                'approver_identity' => 'self-declared (not authenticated in this demo)',
                'reason' => $decision->reason,
            ],
            'audit_trail' => $calculation->auditRecords->map(fn (AuditRecord $audit) => [
                'audit_id' => $audit->uuid,
                'event' => $audit->event,
                'recorded_at' => $audit->created_at->toIso8601String(),
                'explanation' => $audit->explanation,
                'result' => $audit->result_snapshot,
                'policy_version' => $audit->policy_version,
            ])->all(),
        ];
    }
}
