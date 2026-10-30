<?php

declare(strict_types=1);

namespace ExactMoney\Tests;

use ExactMoney\InvalidAmount;
use ExactMoney\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use RoundingMode;

final class ExactTest extends TestCase
{
    /** @return iterable<array{string}> */
    public static function exactDecimals(): iterable
    {
        yield ['0'];
        yield ['5'];
        yield ['-5'];
        yield ['0.000100912'];
        yield ['-1234.50'];
        yield ['99999999.999999999'];
    }

    #[DataProvider('exactDecimals')]
    public function test_an_exact_decimal_comes_back_as_given(string $amount): void
    {
        $this->assertSame($amount, Money::of($amount));
    }

    public function test_an_integer_comes_back_as_a_string(): void
    {
        $this->assertSame('5', Money::of(5));
        $this->assertSame('-5', Money::of(-5));
    }

    /** @return iterable<array{string|float}> */
    public static function notExact(): iterable
    {
        yield 'a float' => [0.5];
        yield 'a whole float' => [5.0];
        yield 'a float cast to a string' => ['7.0E-5'];
        yield 'capital exponent' => ['1E3'];
        yield 'small exponent' => ['1e3'];
        yield 'plus sign' => ['+5'];
        yield 'no whole part' => ['.5'];
        yield 'no fraction' => ['5.'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["5\n"];
        yield 'leading space' => [' 5'];
        yield 'grouped' => ['1,000.00'];
        yield 'printed' => ['$5'];
        yield 'two signs' => ['--5'];
        yield 'hexadecimal' => ['0x1A'];
        yield 'arabic digits' => ['١٢'];
        yield 'not a number' => ['NAN'];
        yield 'infinite' => ['INF'];
    }

    #[DataProvider('notExact')]
    public function test_anything_else_is_refused(string|float $amount): void
    {
        $this->expectException(InvalidAmount::class);

        Money::of($amount);
    }

    #[DataProvider('notExact')]
    public function test_every_method_refuses_it_the_same_way(string|float $amount): void
    {
        foreach ([
            static fn () => Money::cut($amount, 2),
            static fn () => Money::percent($amount, '50'),
            static fn () => Money::percent('5', $amount),
            static fn () => Money::balance($amount),
            static fn () => Money::price($amount),
            static fn () => Money::compact($amount),
        ] as $call) {
            try {
                $call();
                $this->fail('Refused nothing.');
            } catch (InvalidAmount) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cut_truncates_unless_told_otherwise(): void
    {
        $this->assertSame('1.99', Money::cut('1.999', 2));
        $this->assertSame('-1.99', Money::cut('-1.999', 2));
        $this->assertSame('0.000100912', Money::cut('0.00010091250', 9));
    }

    public function test_cut_pads_to_the_decimals_asked_for(): void
    {
        $this->assertSame('5.00', Money::cut('5', 2));
        $this->assertSame('1234.567890', Money::cut('1234.56789', 6));
        $this->assertSame('5', Money::cut('5.99', 0));
    }

    public function test_cut_takes_the_rounding_mode_at_the_call(): void
    {
        $this->assertSame('1234.57', Money::cut('1234.56789', 2, RoundingMode::HalfAwayFromZero));
        $this->assertSame('0.00002', Money::cut('0.000015', 5, RoundingMode::HalfAwayFromZero));
        $this->assertSame('0.12', Money::cut('0.125', 2, RoundingMode::HalfEven));
    }

    public function test_a_cut_never_leaves_a_negative_zero(): void
    {
        $this->assertSame('0.00', Money::cut('-0.001', 2));
    }

    public function test_percent_is_cut_only_once(): void
    {
        $this->assertSame('0.00002', Money::percent('0.00002', '75', 5, RoundingMode::HalfAwayFromZero));
        $this->assertSame('0.000100912', Money::percent('0.001345500', '7.5'));

        // Rounded at 3 places first it would be 1.445, and then 1.45.
        $this->assertSame('1.44', Money::percent('1.4445', '100', 2, RoundingMode::HalfAwayFromZero));
        $this->assertSame('1.44', Money::percent('1.4449999999999', '100', 2, RoundingMode::HalfAwayFromZero));
    }

    public function test_percent_of_a_negative_amount_keeps_its_sign(): void
    {
        $this->assertSame('-0.15', Money::percent('-1.5', '10', 2));
    }

    public function test_percent_loses_no_digit_before_the_cut(): void
    {
        $this->assertSame(
            bcdiv(bcmul('0.123456789', '12.345678', 40), '100', 17),
            Money::percent('0.123456789', '12.345678', 17),
        );
    }

    public function test_percent_matches_how_nine_decimal_credits_are_worked_out_today(): void
    {
        foreach (['0.000000001', '0.000100912', '0.001345500', '1.234567890', '99999.999999999'] as $amount) {
            foreach (['0.000001', '7.5', '12.345678', '100'] as $percent) {
                $this->assertSame(bcdiv(bcmul($amount, $percent, 9), '100', 9), Money::percent($amount, $percent), "{$percent}% of {$amount}");
            }
        }
    }

    public function test_percent_matches_how_five_decimal_credits_are_worked_out_today(): void
    {
        foreach (['0.00001', '0.00002', '0.00007', '0.00015', '0.12345', '1.23456', '9.99999', '1234.56789'] as $amount) {
            foreach (['0', '1', '7.5', '12.5', '33.33333', '50', '75', '100'] as $percent) {
                $this->assertSame(
                    bcround(bcdiv(bcmul($amount, $percent, 20), '100', 22), 5),
                    Money::percent($amount, $percent, 5, RoundingMode::HalfAwayFromZero),
                    "{$percent}% of {$amount}",
                );
            }
        }
    }
}
