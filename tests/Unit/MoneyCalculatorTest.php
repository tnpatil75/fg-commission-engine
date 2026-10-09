<?php

namespace Tests\Unit;

use App\Commission\Money\Currency;
use App\Commission\Money\InvalidMoneyInput;
use App\Commission\Money\MoneyCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyCalculatorTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_it_parses_decimal_amount_strings_into_minor_units(string $input, int $expectedMinor): void
    {
        $this->assertSame($expectedMinor, MoneyCalculator::parseAmount($input, Currency::USD));
    }

    public static function validAmounts(): array
    {
        return [
            'whole number' => ['1', 100],
            'smallest amount' => ['0.01', 1],
            'one decimal' => ['1250.5', 125050],
            'two decimals' => ['1250.50', 125050],
            'maximum' => ['999999999999.99', MoneyCalculator::MAX_AMOUNT_MINOR],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_invalid_amounts_instead_of_correcting_them(string $input, string $expectedMessage): void
    {
        $this->expectException(InvalidMoneyInput::class);
        $this->expectExceptionMessage($expectedMessage);

        MoneyCalculator::parseAmount($input, Currency::AED);
    }

    public static function invalidAmounts(): array
    {
        return [
            'empty' => ['', 'Enter an eligible sale amount'],
            'zero' => ['0', 'greater than zero'],
            'zero with decimals' => ['0.00', 'greater than zero'],
            'negative' => ['-1', 'greater than zero'],
            'negative fraction' => ['-0.01', 'greater than zero'],
            'three decimals' => ['1.005', 'at most 2 decimal places'],
            'oversized' => ['1000000000000', 'must not exceed 999,999,999,999.99'],
            'oversized with decimals' => ['1000000000000.00', 'must not exceed'],
            'exponent' => ['1e3', 'plain decimal number'],
            'thousands separator' => ['1,000', 'plain decimal number'],
            'leading space' => [' 1', 'plain decimal number'],
            'plus sign' => ['+1', 'plain decimal number'],
            'missing whole part' => ['.5', 'plain decimal number'],
            'trailing point' => ['5.', 'plain decimal number'],
            'letters' => ['abc', 'plain decimal number'],
            'leading zero' => ['01', 'leading zeros'],
        ];
    }

    #[DataProvider('validRates')]
    public function test_it_parses_percentages_into_basis_points(string $input, int $expectedBps): void
    {
        $this->assertSame($expectedBps, MoneyCalculator::parseRate($input));
    }

    public static function validRates(): array
    {
        return [
            'zero' => ['0', 0],
            'zero with decimals' => ['0.00', 0],
            'smallest' => ['0.01', 1],
            'one decimal' => ['2.5', 250],
            'whole' => ['5', 500],
            'hundred' => ['100', 10_000],
            'hundred with decimals' => ['100.00', 10_000],
        ];
    }

    #[DataProvider('invalidRates')]
    public function test_it_rejects_invalid_rates(string $input): void
    {
        $this->expectException(InvalidMoneyInput::class);

        MoneyCalculator::parseRate($input);
    }

    public static function invalidRates(): array
    {
        return [
            'empty' => [''], 'above 100' => ['100.01'], 'far above 100' => ['1000'], 'negative' => ['-1'],
            'percent sign' => ['5%'], 'three decimals' => ['1.234'], 'letters' => ['abc'], 'leading zero' => ['05'],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_it_rounds_once_half_up_to_the_nearest_minor_unit(int $amountMinor, int $rateBps, int $expected): void
    {
        $this->assertSame($expected, MoneyCalculator::commission($amountMinor, $rateBps));
    }

    public static function roundingCases(): array
    {
        return [
            'exactly half rounds up: 0.01 at 50%' => [1, 5_000, 1],
            'just below half rounds down: 0.01 at 49.99%' => [1, 4_999, 0],
            'one and a half rounds up: 0.03 at 50%' => [3, 5_000, 2],
            'half a cent: 10.00 at 0.05%' => [1_000, 5, 1],
            'below half a cent: 9.99 at 0.05%' => [999, 5, 0],
            'EUR 2,500.50 at 3% = 75.015 -> 75.02' => [250_050, 300, 7_502],
            'EUR 2,500.49 at 3% = 75.0147 -> 75.01' => [250_049, 300, 7_501],
            'zero rate' => [123_456, 0, 0],
            'hundred percent' => [123_456, 10_000, 123_456],
            'maximum amount at 100% does not overflow' => [MoneyCalculator::MAX_AMOUNT_MINOR, 10_000, MoneyCalculator::MAX_AMOUNT_MINOR],
            'maximum amount at 99.99%' => [MoneyCalculator::MAX_AMOUNT_MINOR, 9_999, 99_989_999_999_999],
        ];
    }

    public function test_commission_rejects_out_of_range_inputs(): void
    {
        foreach ([[MoneyCalculator::MAX_AMOUNT_MINOR + 1, 100], [-1, 100], [100, 10_001], [100, -1]] as [$amount, $rate]) {
            try {
                MoneyCalculator::commission($amount, $rate);
                $this->fail("Expected rejection for amount {$amount} and rate {$rate}.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_formatting_uses_integer_arithmetic(): void
    {
        $this->assertSame('1,234,567.80', MoneyCalculator::format(123_456_780, Currency::USD));
        $this->assertSame('0.05', MoneyCalculator::format(5, Currency::USD));
        $this->assertSame('999,999,999,999.99', MoneyCalculator::format(MoneyCalculator::MAX_AMOUNT_MINOR, Currency::INR));
        $this->assertSame('1234567.80', MoneyCalculator::toDecimalString(123_456_780, Currency::EUR));
        $this->assertSame('2.50', MoneyCalculator::rateToPercent(250));
        $this->assertSame('0.00', MoneyCalculator::rateToPercent(0));
        $this->assertSame('100.00', MoneyCalculator::rateToPercent(10_000));
    }

    public function test_maximum_intermediate_product_fits_in_a_64_bit_integer(): void
    {
        $this->assertSame(8, PHP_INT_SIZE, 'The supported runtime is 64-bit PHP.');
        $this->assertLessThan(
            intdiv(PHP_INT_MAX, MoneyCalculator::MAX_RATE_BPS),
            MoneyCalculator::MAX_AMOUNT_MINOR + 1,
        );
    }
}
