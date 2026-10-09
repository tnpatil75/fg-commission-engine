<?php

namespace App\Rules;

use App\Commission\Money\Currency;
use App\Commission\Money\InvalidMoneyInput;
use App\Commission\Money\MoneyCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A positive decimal amount string within the supported range, e.g. "1250.50". */
class DecimalAmount implements ValidationRule
{
    public function __construct(private readonly ?Currency $currency) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The eligible sale amount must be sent as a decimal string, for example "1250.50".');

            return;
        }

        try {
            MoneyCalculator::parseAmount($value, $this->currency ?? Currency::USD);
        } catch (InvalidMoneyInput $e) {
            $fail($e->getMessage());
        }
    }
}
