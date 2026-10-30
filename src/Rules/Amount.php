<?php

declare(strict_types=1);

namespace ExactMoney\Rules;

use Closure;
use ExactMoney\Money;
use Illuminate\Contracts\Validation\ValidationRule;

/** Stricter than `numeric`, which lets "1e3", "+5" and ".5" through. Never negative. */
final readonly class Amount implements ValidationRule
{
    private string $max;

    public function __construct(private int $decimals = Money::SCALE, string|int|float|bool $max = '999999999')
    {
        $this->max = Money::of($max);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fraction = $this->decimals > 0 ? "(?:\\.\\d{1,{$this->decimals}})?" : '';

        if ((! is_string($value) && ! is_int($value)) || preg_match("/^-?\\d+{$fraction}$/D", (string)$value) !== 1) {
            $fail('validation.decimal')->translate(['decimal' => $this->decimals > 0 ? "0-{$this->decimals}" : '0']);
        } elseif (str_starts_with((string)$value, '-')) {
            $fail('validation.min.numeric')->translate(['min' => '0']);
        } elseif (bccomp(Money::of($value), $this->max, $this->decimals) > 0) {
            $fail('validation.max.numeric')->translate(['max' => $this->max]);
        }
    }
}
