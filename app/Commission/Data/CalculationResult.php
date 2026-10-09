<?php

namespace App\Commission\Data;

use App\Commission\Money\Currency;
use App\Commission\Money\MoneyCalculator;
use App\Commission\Outcome;
use App\Models\Calculation;
use Carbon\CarbonImmutable;

/**
 * The original outcome of a calculation, as shown on screen and returned by the API.
 * Pending outcomes carry no rate, amount, payer or payee.
 */
final readonly class CalculationResult
{
    /**
     * @param  array{code: string, name: string}|null  $payer
     * @param  array{code: string, name: string}|null  $payee
     * @param  array<string, mixed>|null  $rule
     */
    public function __construct(
        public string $calculationId,
        public Outcome $outcome,
        public int $eligibleAmountMinor,
        public Currency $currency,
        public ?int $rateBps,
        public ?int $commissionMinor,
        public ?array $payer,
        public ?array $payee,
        public ?array $rule,
        public string $explanation,
        public string $policyVersion,
        public CarbonImmutable $calculatedAt,
    ) {}

    public static function fromModel(Calculation $calculation): self
    {
        $audit = $calculation->originalAudit;
        $result = $audit->result_snapshot;

        return new self(
            calculationId: $calculation->uuid,
            outcome: $calculation->outcome,
            eligibleAmountMinor: $calculation->eligible_amount_minor,
            currency: $calculation->currency,
            rateBps: $calculation->rate_bps,
            commissionMinor: $calculation->commission_minor,
            payer: $result['payer'],
            payee: $result['payee'],
            rule: $audit->rule_snapshot,
            explanation: $audit->explanation,
            policyVersion: $calculation->policy_version,
            calculatedAt: $calculation->created_at,
        );
    }

    public function isPending(): bool
    {
        return $this->outcome->isPending();
    }

    public function eligibleAmountDisplay(): string
    {
        return $this->currency->value.' '.MoneyCalculator::format($this->eligibleAmountMinor, $this->currency);
    }

    public function rateDisplay(): ?string
    {
        return $this->rateBps === null ? null : MoneyCalculator::rateToPercent($this->rateBps).'%';
    }

    public function commissionDisplay(): ?string
    {
        return $this->commissionMinor === null
            ? null : $this->currency->value.' '.MoneyCalculator::format($this->commissionMinor, $this->currency);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'calculation_id' => $this->calculationId,
            'calculated_at' => $this->calculatedAt->toIso8601String(),
            'outcome' => $this->outcome->value,
            'outcome_label' => $this->outcome->label(),
            'pending_manual_decision' => $this->isPending(),
            'eligible_amount' => MoneyCalculator::toDecimalString($this->eligibleAmountMinor, $this->currency),
            'currency' => $this->currency->value,
            'rate_percent' => $this->rateBps === null ? null : MoneyCalculator::rateToPercent($this->rateBps),
            'rate_bps' => $this->rateBps,
            'commission_amount' => $this->commissionMinor === null
                ? null : MoneyCalculator::toDecimalString($this->commissionMinor, $this->currency),
            'commission_minor' => $this->commissionMinor,
            'payer' => $this->payer,
            'payee' => $this->payee,
            'rule' => $this->rule === null ? null : [
                'rule_id' => $this->rule['rule_id'],
                'version' => $this->rule['version'],
                'reference' => $this->rule['reference'],
                'kind' => $this->rule['kind'],
            ],
            'explanation' => $this->explanation,
            'policy_version' => $this->policyVersion,
        ];
    }
}
