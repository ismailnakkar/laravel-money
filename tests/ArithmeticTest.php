<?php

declare(strict_types=1);

namespace ExactMoney\Tests;

use DivisionByZeroError;
use ExactMoney\InvalidAmount;
use ExactMoney\Money;
use PHPUnit\Framework\Attributes\TestWith;
use TypeError;

final class ArithmeticTest extends TestCase
{
    public function test_of_hands_a_money_back_as_it_is(): void
    {
        $amount = Money::of('1.5');

        $this->assertSame($amount, Money::of($amount));
    }

    public function test_add_keeps_the_longer_scale_with_no_scale_to_forget(): void
    {
        $this->assertSame('0.30000', Money::of('0.10000')->add('0.2')->value);
        $this->assertSame('0.3', Money::of('0.1')->add(Money::of('0.2'))->value);
        $this->assertSame('6.5', Money::of('1.5')->add(5)->value);
    }

    public function test_subtract_can_go_below_zero(): void
    {
        $this->assertSame('-0.10000', Money::of('0.10000')->subtract('0.2')->value);
    }

    public function test_multiply_keeps_every_digit(): void
    {
        $this->assertSame('0.00450000', Money::of('1.50000')->multiply('0.003')->value);
    }

    public function test_divide_truncates_at_nine_decimals_unless_told_otherwise(): void
    {
        $this->assertSame('0.333333333', Money::of('1')->divide('3')->value);
        $this->assertSame('0.33', Money::of('1')->divide(3, 2)->value);
        $this->assertSame('2.500000000', Money::of('10')->divide('4')->value);
    }

    public function test_dividing_by_zero_throws(): void
    {
        $this->expectException(DivisionByZeroError::class);

        Money::of('1')->divide('0');
    }

    public function test_nothing_changes_the_amount_it_is_called_on(): void
    {
        $amount = Money::of('1.5');

        $amount->add('1');
        $amount->subtract('1');
        $amount->multiply('2');
        $amount->divide('2');
        $amount->cut(0);
        $amount->percent('10');

        $this->assertSame('1.5', $amount->value);
    }

    public function test_sum_adds_any_mix_exactly(): void
    {
        $this->assertSame('3.60000', Money::sum(['0.10000', 2, Money::of('1.5')])->value);
        $this->assertSame('0.3', Money::sum((static fn () => yield from ['0.1', '0.2'])())->value);
    }

    public function test_the_sum_of_nothing_is_zero(): void
    {
        $this->assertSame('0', Money::sum([])->value);
        $this->assertTrue(Money::zero()->isZero());
    }

    public function test_sum_refuses_a_float_among_exact_amounts(): void
    {
        $this->expectException(InvalidAmount::class);

        Money::sum(['0.1', 0.2]);
    }

    #[TestWith(['1', '2', -1])]
    #[TestWith(['2', '1', 1])]
    #[TestWith(['1.50', '1.5', 0])]
    #[TestWith(['-1', '0', -1])]
    public function test_compare_orders_two_amounts(string $left, string $right, int $order): void
    {
        $this->assertSame($order, Money::of($left)->compare($right));
    }

    /** A float, or a string compared with `==`, calls these equal. */
    public function test_compare_sees_every_digit(): void
    {
        $this->assertFalse(Money::of('1.0000000000000000001')->equals('1.0000000000000000002'));
        $this->assertTrue(Money::of('1.0000000000000000001')->isLessThan('1.0000000000000000002'));
    }

    public function test_each_comparison_reads_as_named(): void
    {
        $amount = Money::of('5.00');

        $this->assertTrue($amount->equals('5'));
        $this->assertTrue($amount->isGreaterThan('4.99'));
        $this->assertFalse($amount->isGreaterThan('5'));
        $this->assertTrue($amount->isLessThan('5.01'));
        $this->assertFalse($amount->isLessThan('5'));
        $this->assertTrue($amount->isAtLeast('5'));
        $this->assertFalse($amount->isAtLeast('5.01'));
        $this->assertTrue($amount->isAtMost('5'));
        $this->assertFalse($amount->isAtMost('4.99'));
    }

    #[TestWith(['0', true, false, false])]
    #[TestWith(['0.00000', true, false, false])]
    #[TestWith(['0.00001', false, true, false])]
    #[TestWith(['-0.00001', false, false, true])]
    public function test_a_sign_is_a_question_an_amount_answers(string $amount, bool $zero, bool $positive, bool $negative): void
    {
        $this->assertSame([$zero, $positive, $negative], [Money::of($amount)->isZero(), Money::of($amount)->isPositive(), Money::of($amount)->isNegative()]);
    }

    public function test_it_prints_and_encodes_as_its_digits(): void
    {
        $this->assertSame('0.10000', (string)Money::of('0.10000'));
        $this->assertSame('{"amount":"0.10000"}', json_encode(['amount' => Money::of('0.10000')]));
    }

    public function test_it_survives_a_queue(): void
    {
        $this->assertSame('0.10000', unserialize(serialize(Money::of('0.10000')))->value);
    }

    /** The point of an object: `+` would turn a string amount into a float without a word. */
    public function test_a_plain_operator_fails_loudly(): void
    {
        $this->expectException(TypeError::class);

        $amount = Money::of('0.1');

        /** @phpstan-ignore binaryOp.invalid */
        $amount + $amount;
    }
}
