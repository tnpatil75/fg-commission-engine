<?php

namespace App\Rules;

use App\Commission\Money\InvalidMoneyInput;
use App\Commission\Money\MoneyCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A percentage string from 0 to 100 with at most two decimals, e.g. "2.5". */
class PercentageRate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The rate must be sent as a decimal string, for example "2.5".');

            return;
        }

        try {
            MoneyCalculator::parseRate($value);
        } catch (InvalidMoneyInput $e) {
            $fail($e->getMessage());
        }
    }
}
