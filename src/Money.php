<?php

declare(strict_types=1);

namespace ExactMoney;

use BcMath\Number;
use Illuminate\Contracts\Database\Eloquent\Castable;
use JsonSerializable;
use RoundingMode;
use Stringable;

/** An exact decimal. Immutable: every method hands back a new amount. */
final readonly class Money implements Castable, JsonSerializable, Stringable
{
    /** The scale to divide at: no money column keeps more. */
    public const int SCALE = 9;

    private const array UNITS = ['B' => '1000000000', 'M' => '1000000', 'K' => '1000'];

    /** @param  numeric-string  $value */
    private function __construct(public string $value) {}

    /**
     * The amount, once it is known to be an exact decimal. Every method checks through here.
     *
     * A float or a bool is typed in only to be refused: without it, a file with no strict_types hands 99.99 over as the
     * int 99, and false as 0.
     */
    public static function of(self|string|int|float|bool $amount): self
    {
        if ($amount instanceof self) {
            return $amount;
        }

        if (is_float($amount) || is_bool($amount)) {
            throw new InvalidAmount(get_debug_type($amount) . ' ' . var_export($amount, true));
        }

        $exact = (string)$amount;

        // is_numeric is for phpstan; the regex is the check.
        if (! is_numeric($exact) || preg_match('/^-?\d+(?:\.\d+)?$/D', $exact) !== 1) {
            throw new InvalidAmount($exact);
        }

        return new self($exact);
    }

    public static function zero(): self
    {
        return new self('0');
    }

    /** @param  iterable<self|string|int|float|bool>  $amounts */
    public static function sum(iterable $amounts): self
    {
        $sum = self::zero();

        foreach ($amounts as $amount) {
            $sum = $sum->add($amount);
        }

        return $sum;
    }

    public function add(self|string|int|float|bool $amount): self
    {
        return self::exact($this->number()->add(self::of($amount)->number()));
    }

    public function subtract(self|string|int|float|bool $amount): self
    {
        return self::exact($this->number()->sub(self::of($amount)->number()));
    }

    public function multiply(self|string|int|float|bool $factor): self
    {
        return self::exact($this->number()->mul(self::of($factor)->number()));
    }

    /** Truncated at $decimals, as bcdiv() is. */
    public function divide(self|string|int|float|bool $divisor, int $decimals = self::SCALE): self
    {
        return new self(bcdiv($this->value, self::of($divisor)->value, $decimals));
    }

    /** Pads to $decimals. Truncates unless told otherwise, so a printed or paid figure is never larger than the stored one. */
    public function cut(int $decimals, RoundingMode $mode = RoundingMode::TowardsZero): self
    {
        return new self(bcround($this->value, $decimals, $mode));
    }

    /** The exact product, cut once: rounding twice can land on the other side of a tie. */
    public function percent(self|string|int|float|bool $percent, int $decimals = self::SCALE, RoundingMode $mode = RoundingMode::TowardsZero): self
    {
        return self::exact($this->number()->mul(self::of($percent)->number())->div('100'))->cut($decimals, $mode);
    }

    /** Negative, zero or positive, as `<=>` is. */
    public function compare(self|string|int|float|bool $amount): int
    {
        return $this->number()->compare(self::of($amount)->number());
    }

    public function equals(self|string|int|float|bool $amount): bool
    {
        return $this->compare($amount) === 0;
    }

    public function isGreaterThan(self|string|int|float|bool $amount): bool
    {
        return $this->compare($amount) > 0;
    }

    public function isLessThan(self|string|int|float|bool $amount): bool
    {
        return $this->compare($amount) < 0;
    }

    public function isAtLeast(self|string|int|float|bool $amount): bool
    {
        return $this->compare($amount) >= 0;
    }

    public function isAtMost(self|string|int|float|bool $amount): bool
    {
        return $this->compare($amount) <= 0;
    }

    public function isZero(): bool
    {
        return $this->equals(0);
    }

    public function isPositive(): bool
    {
        return $this->isGreaterThan(0);
    }

    public function isNegative(): bool
    {
        return $this->isLessThan(0);
    }

    public static function balance(self|string|int|float|bool $amount, ?int $decimals = null): string
    {
        return self::format($amount, $decimals ?? config()->integer('money.balance_decimals'));
    }

    public static function price(self|string|int|float|bool $amount): string
    {
        return self::format($amount, 2);
    }

    public static function compact(self|string|int|float|bool $amount): string
    {
        $amount = self::of($amount)->value;
        $size = ltrim($amount, '-');

        foreach (self::UNITS as $suffix => $unit) {
            if (bccomp($size, $unit, 0) >= 0) {
                return preg_replace('/\.0$/', '', self::format(bcdiv($amount, $unit, 1), 1)) . $suffix;
            }
        }

        return self::format($amount, 0);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /** @param  array<int, string>  $arguments */
    public static function castUsing(array $arguments): MoneyCast
    {
        return new MoneyCast;
    }

    private function number(): Number
    {
        return new Number($this->value);
    }

    private static function exact(Number $number): self
    {
        return self::of($number->value);
    }

    /** Grouped by hand, on the string: `number_format` takes a float. */
    private static function format(self|string|int|float|bool $amount, int $decimals): string
    {
        [$whole, $fraction] = explode('.', self::of($amount)->cut($decimals)->value . '.');

        $sign = str_starts_with($whole, '-') ? '-' : '';
        $grouped = strrev(implode(',', str_split(strrev(ltrim($whole, '-')), 3)));

        return $sign . config()->string('money.symbol') . $grouped . ($decimals > 0 ? '.' . $fraction : '');
    }
}
