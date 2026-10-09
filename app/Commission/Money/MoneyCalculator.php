<?php

namespace App\Commission\Money;

use InvalidArgumentException;

/**
 * Integer-only money arithmetic: amounts in minor units (cents, fils, paise), rates in basis points.
 *
 * No floating-point values are used anywhere, so results are exact and repeatable.
 */
final class MoneyCalculator
{
    public const POLICY_VERSION = 'rounding-v1';

    public const POLICY_DESCRIPTION = 'commission = eligible amount (minor units) × rate (basis points) ÷ 10,000, '
        .'rounded once to the nearest minor unit, half up';

    /** 100 basis points = 1%, so 10,000 basis points = 100%. */
    public const BASIS_POINTS_PER_WHOLE = 10_000;

    public const MAX_RATE_BPS = 10_000;

    /**
     * 999,999,999,999.99 in a two-decimal currency. The largest intermediate product is
     * 99,999,999,999,999 × 10,000 + 5,000 ≈ 1.0 × 10^18, below PHP_INT_MAX (≈ 9.22 × 10^18) on 64-bit PHP.
     */
    public const MAX_AMOUNT_MINOR = 99_999_999_999_999;

    public const MAX_AMOUNT_DISPLAY = '999,999,999,999.99';

    private const MAX_WHOLE_DIGITS = 12;

    /**
     * Parses a decimal string such as "1250.50" into minor units. Rejects rather than corrects bad input.
     */
    public static function parseAmount(string $value, Currency $currency): int
    {
        $decimals = $currency->minorUnits();

        if ($value === '') {
            throw new InvalidMoneyInput('Enter an eligible sale amount.');
        }
        if (str_starts_with($value, '-')) {
            throw new InvalidMoneyInput('The eligible sale amount must be greater than zero.');
        }
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $parts)) {
            throw new InvalidMoneyInput('The eligible sale amount must be a plain decimal number such as 1250.50 (no spaces, commas, signs or exponents).');
        }

        [$whole, $fraction] = [$parts[1], $parts[2] ?? ''];

        if (strlen($whole) > 1 && $whole[0] === '0') {
            throw new InvalidMoneyInput('The eligible sale amount must not have leading zeros.');
        }
        if (strlen($fraction) > $decimals) {
            throw new InvalidMoneyInput("The eligible sale amount must have at most {$decimals} decimal places.");
        }
        if (strlen($whole) > self::MAX_WHOLE_DIGITS) {
            throw new InvalidMoneyInput('The eligible sale amount must not exceed '.self::MAX_AMOUNT_DISPLAY.'.');
        }

        $minor = (int) $whole * (10 ** $decimals) + (int) str_pad($fraction, $decimals, '0');

        if ($minor === 0) {
            throw new InvalidMoneyInput('The eligible sale amount must be greater than zero.');
        }

        return $minor;
    }

    /**
     * Parses a percentage string from "0" to "100" with at most two decimals into basis points.
     */
    public static function parseRate(string $value): int
    {
        if ($value === '') {
            throw new InvalidMoneyInput('Enter a rate.');
        }
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $parts)) {
            throw new InvalidMoneyInput('The rate must be a percentage between 0 and 100, such as 2.5 (no % sign).');
        }

        [$whole, $fraction] = [$parts[1], $parts[2] ?? ''];

        if (strlen($whole) > 1 && $whole[0] === '0') {
            throw new InvalidMoneyInput('The rate must not have leading zeros.');
        }
        if (strlen($fraction) > 2) {
            throw new InvalidMoneyInput('The rate must have at most 2 decimal places.');
        }
        if (strlen($whole) > 3) {
            throw new InvalidMoneyInput('The rate must be between 0% and 100%.');
        }

        $bps = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        if ($bps > self::MAX_RATE_BPS) {
            throw new InvalidMoneyInput('The rate must be between 0% and 100%.');
        }

        return $bps;
    }

    /**
     * Applies a rate to an amount and rounds once, half up, to the nearest minor unit.
     */
    public static function commission(int $amountMinor, int $rateBps): int
    {
        if ($amountMinor < 0 || $amountMinor > self::MAX_AMOUNT_MINOR) {
            throw new InvalidArgumentException('Amount is outside the supported range.');
        }
        if ($rateBps < 0 || $rateBps > self::MAX_RATE_BPS) {
            throw new InvalidArgumentException('Rate is outside the supported range.');
        }

        return intdiv($amountMinor * $rateBps + intdiv(self::BASIS_POINTS_PER_WHOLE, 2), self::BASIS_POINTS_PER_WHOLE);
    }

    /** "1234567.8" style plain decimal, e.g. 123456780 → "1234567.80". */
    public static function toDecimalString(int $minor, Currency $currency): string
    {
        $scale = 10 ** $currency->minorUnits();

        return intdiv($minor, $scale).'.'.str_pad((string) ($minor % $scale), $currency->minorUnits(), '0', STR_PAD_LEFT);
    }

    /** Display format with thousands separators, e.g. 123456780 → "1,234,567.80". */
    public static function format(int $minor, Currency $currency): string
    {
        [$whole, $fraction] = explode('.', self::toDecimalString($minor, $currency));

        return strrev(implode(',', str_split(strrev($whole), 3))).'.'.$fraction;
    }

    /** Basis points as a percentage string, e.g. 250 → "2.50". */
    public static function rateToPercent(int $bps): string
    {
        return intdiv($bps, 100).'.'.str_pad((string) ($bps % 100), 2, '0', STR_PAD_LEFT);
    }
}
