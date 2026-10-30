<?php

declare(strict_types=1);

namespace ExactMoney;

use BcMath\Number;
use RoundingMode;

final class Money
{
    /** The scale to calculate at: no money column keeps more. */
    public const int SCALE = 9;

    private const array UNITS = ['B' => '1000000000', 'M' => '1000000', 'K' => '1000'];

    /**
     * The amount as a string, once it is known to be an exact decimal. Every method here checks through this one.
     *
     * A float is typed in only to reach the check and be refused: without it, a file with no strict_types hands 99.99
     * over as the int 99, and a string parameter takes it however the `precision` ini prints it.
     *
     * @return numeric-string
     */
    public static function of(string|int|float $amount): string
    {
        $exact = (string)$amount;

        // is_numeric alone lets "1e3", "+5", ".5" and " 5" through.
        if (is_float($amount) || ! is_numeric($exact) || preg_match('/^-?\d+(?:\.\d+)?$/D', $exact) !== 1) {
            throw new InvalidAmount($exact);
        }

        return $exact;
    }

    /** `bcround` that truncates unless told otherwise: a printed or paid figure never exceeds the stored one. */
    public static function cut(string|int|float $amount, int $decimals, RoundingMode $mode = RoundingMode::TowardsZero): string
    {
        return bcround(self::of($amount), $decimals, $mode);
    }

    /** The exact product, cut once: rounding twice can land on the other side of a tie. */
    public static function percent(string|int|float $amount, string|int|float $percent, int $decimals = self::SCALE, RoundingMode $mode = RoundingMode::TowardsZero): string
    {
        return self::cut(new Number(self::of($amount))->mul(self::of($percent))->div('100')->value, $decimals, $mode);
    }

    /** Pass $decimals where the shown decimals can change while a worker runs: config is read once per process. */
    public static function balance(string|int|float $amount, ?int $decimals = null): string
    {
        return self::format($amount, $decimals ?? config()->integer('money.balance_decimals'));
    }

    public static function price(string|int|float $amount): string
    {
        return self::format($amount, 2);
    }

    public static function compact(string|int|float $amount): string
    {
        $amount = self::of($amount);
        $size = self::of(ltrim($amount, '-'));

        foreach (self::UNITS as $suffix => $unit) {
            if (bccomp($size, $unit, 0) >= 0) {
                $sign = str_starts_with($amount, '-') ? '-' : '';

                return $sign . self::symbol() . preg_replace('/\.0$/', '', bcdiv($size, $unit, 1)) . $suffix;
            }
        }

        return self::format($amount, 0);
    }

    /** Grouped by hand, on the string: `number_format` takes a float. */
    private static function format(string|int|float $amount, int $decimals): string
    {
        [$whole, $fraction] = explode('.', self::cut($amount, $decimals) . '.');

        $sign = str_starts_with($whole, '-') ? '-' : '';
        $grouped = strrev(implode(',', str_split(strrev(ltrim($whole, '-')), 3)));

        return $sign . self::symbol() . $grouped . ($decimals > 0 ? '.' . $fraction : '');
    }

    private static function symbol(): string
    {
        return config()->string('money.symbol');
    }
}
