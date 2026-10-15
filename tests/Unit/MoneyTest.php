<?php

namespace Tests\Unit;

use App\Services\Money;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public static function amounts(): array
    {
        return ['cent' => ['0.01', 1], 'tenths' => ['0.1', 10], 'whole' => ['12', 1200], 'large' => ['999999999.99', 99999999999]];
    }

    #[DataProvider('amounts')]
    public function test_converts_decimal_strings_to_exact_cents(string $value, int $expected): void
    {
        $this->assertSame($expected, Money::cents($value));
    }

    public static function invalidAmounts(): array
    {
        return ['negative' => ['-1'], 'exponent' => ['1e2'], 'precision' => ['1.001'], 'oversized' => ['1000000000'], 'empty' => ['']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_unsupported_amounts(string $value): void
    {
        $this->expectException(ValidationException::class);
        Money::cents($value);
    }

    public function test_formats_negative_ledger_totals_without_float_rounding(): void
    {
        $this->assertSame('-12.01', Money::decimal(-1201));
        $this->assertSame('1,234.56', Money::display(123456));
    }

    public function test_calculates_weight_charge_with_upward_cent_rounding(): void
    {
        $this->assertSame(502, Money::fee(['base_charge' => '1.00', 'per_weight' => '10.01', 'minimum_charge' => '0.00', 'additional_charge' => '2.00'], '0.201'));
    }

    public function test_applies_minimum_before_additional_charge(): void
    {
        $this->assertSame(1700, Money::fee(['base_charge' => '5.00', 'per_weight' => '12.00', 'minimum_charge' => '15.00', 'additional_charge' => '2.00'], '0.100'));
    }

    public static function feeBoundaries(): array
    {
        return [
            'exact gram rate' => ['0.001', '10.00', '0.00', '0.00', '0.00', 1],
            'fraction rounds upward' => ['0.001', '0.01', '0.00', '0.00', '0.00', 1],
            'no extra cent on whole result' => ['2.500', '10.00', '0.00', '0.00', '0.00', 2500],
            'minimum plus surcharge' => ['0.001', '10.00', '1.00', '15.00', '2.50', 1750],
            'zero rate still applies base' => ['2.500', '0.00', '5.00', '0.00', '0.00', 500],
        ];
    }

    #[DataProvider('feeBoundaries')]
    public function test_fee_calculation_at_weight_and_rounding_boundaries(string $weight, string $perWeight, string $base, string $minimum, string $additional, int $expected): void
    {
        $this->assertSame($expected, Money::fee(['base_charge' => $base, 'per_weight' => $perWeight, 'minimum_charge' => $minimum, 'additional_charge' => $additional], $weight));
    }

    public static function invalidWeights(): array
    {
        return [['0'], ['-1'], ['0.0001'], ['1e2'], ['100000'], ['']];
    }

    #[DataProvider('invalidWeights')]
    public function test_fee_rejects_invalid_weights(string $weight): void
    {
        $this->expectException(ValidationException::class);
        Money::fee(['base_charge' => '0.00', 'per_weight' => '10.00', 'minimum_charge' => '0.00', 'additional_charge' => '0.00'], $weight);
    }
}
