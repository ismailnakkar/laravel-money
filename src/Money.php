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
     * The amount, once it is known to be an exact decimal. Every method checks through here.
     *
     * A float or a bool is typed in only to be refused: without it, a file with no strict_types hands 99.99 over as the
     * int 99, and false as 0.
     *
     * @return numeric-string
     */
    public static function of(string|int|float|bool $amount): string
    {
        if (is_float($amount) || is_bool($amount)) {
            throw new InvalidAmount(get_debug_type($amount) . ' ' . var_export($amount, true));
        }

        $exact = (string)$amount;

        // is_numeric is for phpstan; the regex is the check.
        if (! is_numeric($exact) || preg_match('/^-?\d+(?:\.\d+)?$/D', $exact) !== 1) {
            throw new InvalidAmount($exact);
        }

        return $exact;
    }

    /** @return numeric-string */
    public static function cut(
        string|int|float|bool $amount,
        int $decimals,
        RoundingMode $mode = RoundingMode::TowardsZero,
    ): string {
        return bcround(self::of($amount), $decimals, $mode);
    }

    /**
     * The exact product, cut once: rounding twice can land on the other side of a tie.
     *
     * @return numeric-string
     */
    public static function percent(
        string|int|float|bool $amount,
        string|int|float|bool $percent,
        int $decimals = self::SCALE,
        RoundingMode $mode = RoundingMode::TowardsZero,
    ): string {
        return self::cut(new Number(self::of($amount))->mul(self::of($percent))->div('100')->value, $decimals, $mode);
    }

    public static function balance(string|int|float|bool $amount, ?int $decimals = null): string
    {
        return self::format($amount, $decimals ?? config()->integer('money.balance_decimals'));
    }

    public static function price(string|int|float|bool $amount): string
    {
        return self::format($amount, 2);
    }

    public static function compact(string|int|float|bool $amount): string
    {
        $amount = self::of($amount);
        $size = ltrim($amount, '-');

        foreach (self::UNITS as $suffix => $unit) {
            if (bccomp($size, $unit, 0) >= 0) {
                return preg_replace('/\.0$/', '', self::format(bcdiv($amount, $unit, 1), 1)) . $suffix;
            }
        }

        return self::format($amount, 0);
    }

    /** Grouped by hand, on the string: `number_format` takes a float. */
    private static function format(string|int|float|bool $amount, int $decimals): string
    {
        [$whole, $fraction] = explode('.', self::cut($amount, $decimals) . '.');

        $sign = str_starts_with($whole, '-') ? '-' : '';
        $grouped = strrev(implode(',', str_split(strrev(ltrim($whole, '-')), 3)));

        return $sign . config()->string('money.symbol') . $grouped . ($decimals > 0 ? '.' . $fraction : '');
    }
}
