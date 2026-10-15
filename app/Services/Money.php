<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function cents(string|int $amount): int
    {
        $amount = (string) $amount;
        if (! preg_match('/^\d{1,9}(\.\d{1,2})?$/D', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Use a non-negative amount with at most 2 decimals (maximum 999,999,999.99).']);
        }
        [$whole,$fraction] = array_pad(explode('.', $amount), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $cents): string
    {
        $s = self::decimal($cents);
        [$a,$b] = explode('.', $s);

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $a).'.'.$b;
    }

    public static function fee(array $rate, string $weight): int
    {
        if (! preg_match('/^\d{1,5}(\.\d{1,3})?$/D', $weight) || bccomp($weight, '0', 3) <= 0) {
            throw ValidationException::withMessages(['actual_weight' => 'Enter a positive weight with at most 3 decimals, below 100,000.']);
        }
        $weighted = bcmul($weight, (string) self::cents($rate['per_weight']), 3);
        $rounded = (int) bcdiv(bcadd($weighted, '0.999', 3), '1', 0);
        $price = max(self::cents($rate['minimum_charge']), self::cents($rate['base_charge']) + $rounded) + self::cents($rate['additional_charge']);
        if ($price > 99999999999) {
            throw ValidationException::withMessages(['actual_weight' => 'Calculated price exceeds the supported limit.']);
        }

        return $price;
    }
}
