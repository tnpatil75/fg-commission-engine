<?php

namespace App\Commission\Data;

use App\Commission\Money\Currency;
use App\Commission\Money\MoneyCalculator;
use InvalidArgumentException;

/**
 * Validated input for one calculation. Matching uses the four codes only; the plant reference is context.
 */
final readonly class CalculationInput
{
    public function __construct(
        public string $segmentCode,
        public string $regionCode,
        public string $leadCompanyCode,
        public string $saleTypeCode,
        public int $eligibleAmountMinor,
        public Currency $currency,
        public ?string $externalOrderReference = null,
        public ?string $plantReference = null,
    ) {
        if ($eligibleAmountMinor < 1 || $eligibleAmountMinor > MoneyCalculator::MAX_AMOUNT_MINOR) {
            throw new InvalidArgumentException('The eligible amount is outside the supported range.');
        }
    }

    /** Builds the input from user-facing strings, e.g. amount "1250.50" and currency "AED". */
    public static function fromStrings(
        string $segment,
        string $region,
        string $leadCompany,
        string $saleType,
        string $amount,
        string $currency,
        ?string $externalOrderReference = null,
        ?string $plantReference = null,
    ): self {
        $currencyEnum = Currency::tryFrom($currency)
            ?? throw new InvalidArgumentException("Unsupported currency: {$currency}");

        return new self(
            $segment, $region, $leadCompany, $saleType,
            MoneyCalculator::parseAmount($amount, $currencyEnum), $currencyEnum,
            $externalOrderReference, $plantReference,
        );
    }
}
