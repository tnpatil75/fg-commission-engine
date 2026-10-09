<?php

namespace App\Commission\Data;

use App\Commission\Money\MoneyCalculator;
use InvalidArgumentException;

/**
 * A manual decision for a pending calculation. The amount and currency always come from the original calculation.
 */
final readonly class ManualDecisionInput
{
    public function __construct(
        public int $rateBps,
        public string $payerCode,
        public string $payeeCode,
        public string $approverName,
        public string $reason,
    ) {
        if ($rateBps < 0 || $rateBps > MoneyCalculator::MAX_RATE_BPS) {
            throw new InvalidArgumentException('The rate must be between 0% and 100%.');
        }
        if ($payerCode === $payeeCode) {
            throw new InvalidArgumentException('The payer and payee must be different companies.');
        }
        if (trim($approverName) === '' || trim($reason) === '') {
            throw new InvalidArgumentException('An approver name and a reason are required.');
        }
    }
}
