<?php

namespace App\Commission;

/**
 * The original outcome of a calculation. Only "fixed" produces a final rate and amount.
 */
enum Outcome: string
{
    case Fixed = 'fixed';
    case CaseByCase = 'case_by_case';
    case NoMatch = 'no_match';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed rule applied',
            self::CaseByCase => 'Decided case by case — manual approval required',
            self::NoMatch => 'No matching rule — manual review required',
        };
    }

    public function isPending(): bool
    {
        return $this !== self::Fixed;
    }
}
