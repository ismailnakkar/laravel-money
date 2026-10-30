<?php

declare(strict_types=1);

namespace ExactMoney\Rules;

use Closure;
use ExactMoney\Money;
use Illuminate\Contracts\Validation\ValidationRule;

/** A typed amount: never negative, at most $decimals places, at most $max. `numeric` lets "1e3", "+5" and ".5" through; this does not. */
final readonly class Amount implements ValidationRule
{
    public function __construct(private int $decimals = Money::SCALE, private string $max = '999999999') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fraction = $this->decimals > 0 ? "(?:\\.\\d{1,{$this->decimals}})?" : '';

        if ((! is_string($value) && ! is_int($value)) || preg_match("/^\\d+{$fraction}$/D", (string)$value) !== 1) {
            $fail('validation.decimal')->translate(['decimal' => $this->decimals > 0 ? "0-{$this->decimals}" : '0']);
        } elseif (bccomp(Money::of($value), Money::of($this->max), $this->decimals) > 0) {
            $fail('validation.max.numeric')->translate(['max' => $this->max]);
        }
    }
}
