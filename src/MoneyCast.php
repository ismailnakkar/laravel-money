<?php

declare(strict_types=1);

namespace ExactMoney;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads a column as a Money and refuses a float on the way in. increment() and decrement() are what Eloquent calls in
 * place of `+`, which would throw on the object.
 *
 * @implements CastsAttributes<Money, Money|string|int>
 *
 * @internal
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::of($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Money::of($value)->value;
    }

    /** @param  array<string, mixed>  $attributes */
    public function increment(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return Money::of($attributes[$key])->add($value);
    }

    /** @param  array<string, mixed>  $attributes */
    public function decrement(Model $model, string $key, mixed $value, array $attributes): Money
    {
        return Money::of($attributes[$key])->subtract($value);
    }
}
