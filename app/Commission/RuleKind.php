<?php

namespace App\Commission;

enum RuleKind: string
{
    case Fixed = 'fixed';
    case CaseByCase = 'case_by_case';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed percentage',
            self::CaseByCase => 'Decided case by case — manual approval required',
        };
    }
}
