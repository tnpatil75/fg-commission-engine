<?php

namespace App\Commission\Money;

/**
 * Supported currencies. Results are always returned in the input currency (no FX conversion).
 */
enum Currency: string
{
    case AED = 'AED';
    case USD = 'USD';
    case EUR = 'EUR';
    case INR = 'INR';

    public function minorUnits(): int
    {
        return 2;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
