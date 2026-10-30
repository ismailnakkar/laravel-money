<?php

declare(strict_types=1);

namespace ExactMoney\Tests;

use ExactMoney\InvalidAmount;
use ExactMoney\Rules\Amount;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

final class AmountTest extends TestCase
{
    /** @return iterable<array{mixed}> */
    public static function typed(): iterable
    {
        yield ['0'];
        yield ['5'];
        yield [5];
        yield ['5.5'];
        yield ['0.000000001'];
        yield ['999999999'];
        yield ['999999999.000000000'];
    }

    #[DataProvider('typed')]
    public function test_a_plain_amount_passes(mixed $value): void
    {
        $this->assertTrue($this->passes($value, new Amount));
    }

    /** @return iterable<array{mixed}> */
    public static function refused(): iterable
    {
        yield 'small exponent' => ['1e3'];
        yield 'capital exponent' => ['1E3'];
        yield 'plus sign' => ['+5'];
        yield 'no whole part' => ['.5'];
        yield 'no fraction' => ['5.'];
        yield 'negative' => ['-5'];
        yield 'negative integer' => [-5];
        yield 'negative zero' => ['-0'];
        yield 'leading space' => [' 5'];
        yield 'trailing newline' => ["5\n"];
        yield 'grouped' => ['1,000'];
        yield 'too many decimals' => ['0.0000000001'];
        yield 'above the most' => ['999999999.000000001'];
        yield 'a float' => [5.5];
        yield 'a boolean' => [true];
        yield 'a list' => [['5']];
    }

    #[DataProvider('refused')]
    public function test_anything_else_is_refused(mixed $value): void
    {
        $this->assertFalse($this->passes($value, new Amount));
    }

    public function test_it_takes_the_decimals_of_its_column(): void
    {
        $this->assertTrue($this->passes('1.23', new Amount(decimals: 2)));
        $this->assertFalse($this->passes('1.234', new Amount(decimals: 2)));
    }

    public function test_no_decimals_means_a_whole_number(): void
    {
        $this->assertTrue($this->passes('5', new Amount(decimals: 0)));
        $this->assertFalse($this->passes('5.0', new Amount(decimals: 0)));
    }

    public function test_the_most_counts_every_decimal(): void
    {
        $rule = new Amount(decimals: 5, max: '9999999.99999');

        $this->assertTrue($this->passes('9999999.99999', $rule));
        $this->assertFalse($this->passes('10000000', $rule));
        $this->assertFalse($this->passes('9999999.00001', new Amount(decimals: 5, max: '9999999')));
        $this->assertFalse($this->passes('1.0000000001', new Amount(decimals: 12, max: '1')));
    }

    public function test_the_most_can_be_an_integer(): void
    {
        $this->assertTrue($this->passes('100', new Amount(max: 100)));
        $this->assertFalse($this->passes('101', new Amount(max: 100)));
    }

    /** @return iterable<array{string|float|bool}> */
    public static function notAMost(): iterable
    {
        yield 'a float' => [99.99];
        yield 'a boolean' => [true];
        yield 'an exponent' => ['1e3'];
    }

    #[DataProvider('notAMost')]
    public function test_a_most_that_is_not_exact_is_refused_when_the_rule_is_built(string|float|bool $max): void
    {
        $this->expectException(InvalidAmount::class);

        new Amount(max: $max);
    }

    public function test_it_says_what_is_wrong(): void
    {
        $this->assertSame('The amount field must have 0-2 decimal places.', $this->message('1.234', new Amount(decimals: 2)));
        $this->assertSame('The amount field must have 0 decimal places.', $this->message('1.5', new Amount(decimals: 0)));
        $this->assertSame('The amount field must not be greater than 100.', $this->message('101', new Amount(max: '100')));
        $this->assertSame('The amount field must be at least 0.', $this->message('-5', new Amount));
        $this->assertSame('The amount field must be at least 0.', $this->message(-5, new Amount));
        $this->assertSame('The amount field must have 0-2 decimal places.', $this->message('-1.234', new Amount(decimals: 2)));
    }

    public function test_an_empty_optional_field_is_left_to_nullable(): void
    {
        $this->assertTrue(Validator::make(['amount' => null], ['amount' => ['nullable', new Amount]])->passes());
    }

    private function passes(mixed $value, Amount $rule): bool
    {
        return Validator::make(['amount' => $value], ['amount' => [$rule]])->passes();
    }

    private function message(mixed $value, Amount $rule): ?string
    {
        return Validator::make(['amount' => $value], ['amount' => [$rule]])->errors()->first('amount');
    }
}
