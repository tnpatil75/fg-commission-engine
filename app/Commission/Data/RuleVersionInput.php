<?php

namespace App\Commission\Data;

use App\Commission\Money\MoneyCalculator;
use App\Commission\RuleKind;
use InvalidArgumentException;

/**
 * The content of a new rule version: either a fixed rate with an explicit payer and payee,
 * or "decided case by case" with no rate and no payment direction.
 */
final readonly class RuleVersionInput
{
    public function __construct(
        public RuleKind $kind,
        public ?int $rateBps = null,
        public ?string $payerCode = null,
        public ?string $payeeCode = null,
        public ?string $note = null,
    ) {
        if ($kind === RuleKind::Fixed) {
            if ($rateBps === null || $payerCode === null || $payeeCode === null) {
                throw new InvalidArgumentException('A fixed rule needs a rate, a payer and a payee.');
            }
            if ($rateBps < 0 || $rateBps > MoneyCalculator::MAX_RATE_BPS) {
                throw new InvalidArgumentException('The rate must be between 0% and 100%.');
            }
            if ($payerCode === $payeeCode) {
                throw new InvalidArgumentException('The payer and payee must be different companies.');
            }
        } elseif ($rateBps !== null || $payerCode !== null || $payeeCode !== null) {
            throw new InvalidArgumentException('A case-by-case rule must not set a rate, payer or payee.');
        }
    }

    public static function fixed(int $rateBps, string $payerCode, string $payeeCode, ?string $note = null): self
    {
        return new self(RuleKind::Fixed, $rateBps, $payerCode, $payeeCode, $note);
    }

    public static function caseByCase(?string $note = null): self
    {
        return new self(RuleKind::CaseByCase, note: $note);
    }
}
