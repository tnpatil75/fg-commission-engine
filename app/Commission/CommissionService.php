<?php

namespace App\Commission;

use App\Commission\Contracts\RuleRepository;
use App\Commission\Data\CalculationInput;
use App\Commission\Data\CalculationResult;
use App\Commission\Data\ManualDecisionInput;
use App\Commission\Money\Currency;
use App\Commission\Money\MoneyCalculator;
use App\Models\AuditRecord;
use App\Models\Calculation;
use App\Models\Company;
use App\Models\ManualDecision;
use App\Models\MasterRecord;
use App\Models\ProductSegment;
use App\Models\Region;
use App\Models\RuleVersion;
use App\Models\SaleType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Application service used by both the Blade screens and the JSON API.
 *
 * It matches the exact rule, calculates the payable amount, and persists the calculation together with its
 * audit record in one transaction. It also records manual decisions for pending calculations.
 */
final class CommissionService
{
    public function __construct(private readonly RuleRepository $rules) {}

    public function calculate(CalculationInput $input): CalculationResult
    {
        $segment = $this->master(ProductSegment::class, $input->segmentCode, 'product segment');
        $region = $this->master(Region::class, $input->regionCode, 'region');
        $leadCompany = $this->master(Company::class, $input->leadCompanyCode, 'lead company');
        $saleType = $this->master(SaleType::class, $input->saleTypeCode, 'sale type');

        $version = $this->rules->findActiveVersion($segment->id, $region->id, $leadCompany->id, $saleType->id);

        $outcome = match (true) {
            $version === null => Outcome::NoMatch,
            $version->kind === RuleKind::CaseByCase => Outcome::CaseByCase,
            default => Outcome::Fixed,
        };

        $fixed = $outcome === Outcome::Fixed;
        $rateBps = $fixed ? $version->rate_bps : null;
        $commissionMinor = $fixed ? MoneyCalculator::commission($input->eligibleAmountMinor, $rateBps) : null;
        $payer = $fixed ? $version->payer : null;
        $payee = $fixed ? $version->payee : null;

        $inputSnapshot = [
            'segment' => $segment->snapshot(),
            'region' => $region->snapshot(),
            'lead_company' => $leadCompany->snapshot(),
            'sale_type' => $saleType->snapshot(),
            'eligible_amount' => MoneyCalculator::toDecimalString($input->eligibleAmountMinor, $input->currency),
            'eligible_amount_minor' => $input->eligibleAmountMinor,
            'currency' => $input->currency->value,
            'external_order_reference' => $input->externalOrderReference,
            'plant_reference' => $input->plantReference,
        ];

        $explanation = $this->explainCalculation($outcome, $inputSnapshot, $version, $commissionMinor, $input->currency);

        $calculation = DB::transaction(function () use (
            $input, $segment, $region, $leadCompany, $saleType, $outcome, $version,
            $rateBps, $commissionMinor, $payer, $payee, $inputSnapshot, $explanation
        ) {
            $calculation = Calculation::create([
                'product_segment_id' => $segment->id,
                'region_id' => $region->id,
                'lead_company_id' => $leadCompany->id,
                'sale_type_id' => $saleType->id,
                'eligible_amount_minor' => $input->eligibleAmountMinor,
                'currency' => $input->currency,
                'external_order_reference' => $input->externalOrderReference,
                'plant_reference' => $input->plantReference,
                'outcome' => $outcome,
                'rule_version_id' => $version?->id,
                'rate_bps' => $rateBps,
                'commission_minor' => $commissionMinor,
                'payer_company_id' => $payer?->id,
                'payee_company_id' => $payee?->id,
                'policy_version' => MoneyCalculator::POLICY_VERSION,
            ]);

            AuditRecord::create([
                'calculation_id' => $calculation->id,
                'event' => AuditRecord::EVENT_CALCULATED,
                'outcome' => $outcome,
                'explanation' => $explanation,
                'input_snapshot' => $inputSnapshot,
                'rule_snapshot' => $version?->snapshot(),
                'result_snapshot' => $this->resultSnapshot($outcome, $rateBps, $commissionMinor, $input->currency, $payer, $payee),
                'policy_version' => MoneyCalculator::POLICY_VERSION,
            ]);

            return $calculation;
        });

        return CalculationResult::fromModel($calculation->load('originalAudit'));
    }

    /**
     * Appends the single final decision for a pending calculation. The original outcome is never changed.
     */
    public function recordManualDecision(string $calculationUuid, ManualDecisionInput $input): ManualDecision
    {
        $payer = $this->master(Company::class, $input->payerCode, 'payer');
        $payee = $this->master(Company::class, $input->payeeCode, 'payee');

        return DB::transaction(function () use ($calculationUuid, $input, $payer, $payee) {
            // Row lock: a concurrent submission waits here and then sees the first decision.
            $calculation = Calculation::query()->where('uuid', $calculationUuid)->lockForUpdate()->firstOrFail();

            if (! $calculation->outcome->isPending()) {
                throw new BusinessRuleViolation('Manual decisions are only allowed for pending calculations. This calculation used a fixed rule.');
            }
            if ($calculation->manualDecision()->exists()) {
                throw new BusinessRuleViolation('A final decision has already been recorded for this calculation.');
            }

            $commissionMinor = MoneyCalculator::commission($calculation->eligible_amount_minor, $input->rateBps);

            try {
                $decision = ManualDecision::create([
                    'calculation_id' => $calculation->id,
                    'rate_bps' => $input->rateBps,
                    'commission_minor' => $commissionMinor,
                    'currency' => $calculation->currency,
                    'payer_company_id' => $payer->id,
                    'payee_company_id' => $payee->id,
                    'approver_name' => $input->approverName,
                    'reason' => $input->reason,
                    'policy_version' => MoneyCalculator::POLICY_VERSION,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new BusinessRuleViolation('A final decision has already been recorded for this calculation.');
            }

            $original = $calculation->originalAudit;

            AuditRecord::create([
                'calculation_id' => $calculation->id,
                'manual_decision_id' => $decision->id,
                'event' => AuditRecord::EVENT_MANUAL_DECISION,
                'outcome' => $calculation->outcome,
                'explanation' => $this->explainDecision($calculation, $decision, $payer, $payee),
                'input_snapshot' => $original->input_snapshot,
                'rule_snapshot' => $original->rule_snapshot,
                'result_snapshot' => $this->resultSnapshot(
                    $calculation->outcome, $input->rateBps, $commissionMinor, $calculation->currency, $payer, $payee
                ) + [
                    'decision' => [
                        'decision_id' => $decision->uuid,
                        'approver_name' => $decision->approver_name,
                        'approver_identity' => 'self-declared (not authenticated in this demo)',
                        'reason' => $decision->reason,
                        'decided_at' => $decision->decided_at->toIso8601String(),
                    ],
                ],
                'policy_version' => MoneyCalculator::POLICY_VERSION,
            ]);

            return $decision;
        });
    }

    /**
     * @template T of MasterRecord
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function master(string $model, string $code, string $label): MasterRecord
    {
        return $model::query()->where('code', $code)->first()
            ?? throw new BusinessRuleViolation("Unknown {$label} code: {$code}.");
    }

    /** @return array<string, mixed> */
    private function resultSnapshot(
        Outcome $outcome,
        ?int $rateBps,
        ?int $commissionMinor,
        Currency $currency,
        ?Company $payer,
        ?Company $payee,
    ): array {
        return [
            'outcome' => $outcome->value,
            'outcome_label' => $outcome->label(),
            'rate_bps' => $rateBps,
            'rate_percent' => $rateBps === null ? null : MoneyCalculator::rateToPercent($rateBps),
            'commission_minor' => $commissionMinor,
            'commission_amount' => $commissionMinor === null ? null : MoneyCalculator::toDecimalString($commissionMinor, $currency),
            'currency' => $currency->value,
            'payer' => $payer?->snapshot(),
            'payee' => $payee?->snapshot(),
            'policy' => MoneyCalculator::POLICY_DESCRIPTION,
        ];
    }

    /** @param  array<string, mixed>  $input */
    private function explainCalculation(
        Outcome $outcome,
        array $input,
        ?RuleVersion $version,
        ?int $commissionMinor,
        Currency $currency,
    ): string {
        $dimensions = sprintf(
            'segment %s, sale destination %s, lead generated by %s, sale type %s',
            $input['segment']['name'], $input['region']['name'], $input['lead_company']['name'], $input['sale_type']['name'],
        );

        $text = match ($outcome) {
            Outcome::NoMatch => "No active rule matches this exact combination ({$dimensions}). "
                .'Rules must match all four dimensions exactly. No rate has been assumed (missing configuration is '
                .'never treated as 0%); manual review is required.',

            Outcome::CaseByCase => "{$version->reference()} matched exactly on {$dimensions}. "
                .'The rule is marked "decided case by case", so it sets no rate, payer or payee. '
                .'Manual approval is required before any amount is payable.',

            Outcome::Fixed => $this->explainFixed($version, $dimensions, $input, $commissionMinor, $currency),
        };

        if ($input['plant_reference'] !== null) {
            $text .= " Plant reference {$input['plant_reference']} is recorded for context and did not affect rule matching.";
        }

        return $text;
    }

    /** @param  array<string, mixed>  $input */
    private function explainFixed(RuleVersion $version, string $dimensions, array $input, int $commissionMinor, Currency $currency): string
    {
        $rate = MoneyCalculator::rateToPercent($version->rate_bps);
        $amount = MoneyCalculator::format($input['eligible_amount_minor'], $currency);
        $commission = MoneyCalculator::format($commissionMinor, $currency);

        $text = "{$version->reference()} matched exactly on {$dimensions}. "
            ."It sets a fixed rate of {$rate}%, paid by {$version->payer->name} to {$version->payee->name}. "
            ."{$rate}% of {$currency->value} {$amount} = {$currency->value} {$commission}, "
            .'rounded once to the nearest minor unit (half up).';

        if ($version->rate_bps === 0) {
            $text .= ' The rule explicitly configures 0%, so the payable amount is zero; this is a configured result, not a missing rule.';
        }

        return $text;
    }

    private function explainDecision(Calculation $calculation, ManualDecision $decision, Company $payer, Company $payee): string
    {
        $currency = $calculation->currency->value;
        $amount = MoneyCalculator::format($calculation->eligible_amount_minor, $calculation->currency);
        $commission = MoneyCalculator::format($decision->commission_minor, $calculation->currency);
        $rate = MoneyCalculator::rateToPercent($decision->rate_bps);

        return "Manual decision recorded by {$decision->approver_name} (self-declared approver): "
            ."{$rate}% of the original eligible amount {$currency} {$amount} = {$currency} {$commission}, "
            ."paid by {$payer->name} to {$payee->name}, rounded once to the nearest minor unit (half up). "
            ."Reason: {$decision->reason} "
            ."The original outcome \"{$calculation->outcome->label()}\" is preserved unchanged.";
    }
}
