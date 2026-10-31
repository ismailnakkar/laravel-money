<?php

declare(strict_types=1);

namespace ExactMoney\Tests;

use ExactMoney\Money;
use ExactMoney\MoneyServiceProvider;
use Illuminate\Support\ServiceProvider;

final class DisplayTest extends TestCase
{
    public function test_a_balance_keeps_four_decimals_by_default(): void
    {
        $this->assertSame('$10.1234', Money::balance('10.123456789'));
        $this->assertSame('$0.0099', Money::balance('0.009900000'));
    }

    public function test_a_small_earning_is_not_shown_as_nothing(): void
    {
        $this->assertSame('$0.0020', Money::balance('0.002000000'));
    }

    public function test_a_format_takes_a_money_as_it_takes_a_string(): void
    {
        $this->assertSame('$10.1234', Money::balance(Money::of('10.123456789')));
        $this->assertSame('$9.99', Money::price(Money::of('9.999')));
        $this->assertSame('$5.1K', Money::compact(Money::of('5150')));
    }

    public function test_a_price_keeps_two(): void
    {
        $this->assertSame('$9.99', Money::price('9.990000000'));
        $this->assertSame('$18.00', Money::price('18'));
    }

    public function test_every_format_truncates_rather_than_rounds(): void
    {
        $this->assertSame('$1.9999', Money::balance('1.99999'));
        $this->assertSame('$1.99', Money::price('1.999'));
    }

    public function test_a_large_balance_survives_without_a_float(): void
    {
        $this->assertSame('$8,388,608.0100', Money::balance('8388608.010000000'));
        $this->assertSame('$99,999,999.9999', Money::balance('99999999.999999999'));
    }

    public function test_thousands_are_grouped_so_a_site_total_can_be_read(): void
    {
        $this->assertSame('$2,142,825.4318', Money::balance('2142825.431800000'));
        $this->assertSame('$2,139,835.66', Money::price('2139835.660000000'));
        $this->assertSame('$1,000.00', Money::price('1000'));
    }

    public function test_grouping_leaves_small_amounts_and_the_sign_alone(): void
    {
        $this->assertSame('$999.9999', Money::balance('999.999999999'));
        $this->assertSame('-$1,234,567.89', Money::price('-1234567.891'));
    }

    public function test_a_negative_that_cuts_to_nothing_loses_its_sign(): void
    {
        $this->assertSame('$0.00', Money::price('-0.001'));
    }

    public function test_the_config_sets_the_balance_decimals(): void
    {
        config(['money.balance_decimals' => 5]);

        $this->assertSame('$1,234.56789', Money::balance('1234.567891234'));
    }

    public function test_the_config_can_be_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(MoneyServiceProvider::class, 'money-config');

        $this->assertFileExists((string)array_search(config_path('money.php'), $paths, true));
    }

    public function test_the_call_can_name_its_decimals(): void
    {
        $this->assertSame('$1,234.56', Money::balance('1234.567891234', 2));
        $this->assertSame('$12,345', Money::balance('12345.99', 0));
    }

    public function test_the_config_sets_the_symbol(): void
    {
        config(['money.symbol' => '€']);

        $this->assertSame('€9.99', Money::price('9.99'));
        $this->assertSame('-€1.0000', Money::balance('-1'));
        $this->assertSame('€5K', Money::compact('5000'));
    }

    public function test_a_compact_total_truncates_to_one_decimal(): void
    {
        $this->assertSame('$5.1K', Money::compact('5150.000000000'));
        $this->assertSame('$5K', Money::compact('5000.000000000'));
        $this->assertSame('$4.8M', Money::compact('4899999.990000000'));
        $this->assertSame('$1.2B', Money::compact('1234567890.000000000'));
        $this->assertSame('$999', Money::compact('999.990000000'));
    }

    public function test_a_compact_total_keeps_the_zeros_of_its_whole_part(): void
    {
        $this->assertSame('$10K', Money::compact('10000'));
        $this->assertSame('$100M', Money::compact('100000000'));
        $this->assertSame('$300B', Money::compact('300000000000'));
    }

    public function test_a_compact_total_leaves_a_symbol_that_looks_like_a_decimal_alone(): void
    {
        config(['money.symbol' => '.0']);

        $this->assertSame('.05.1K', Money::compact('5150'));
    }

    public function test_a_compact_total_past_its_top_unit_is_still_grouped(): void
    {
        $this->assertSame('$1,234.5B', Money::compact('1234567890000'));
        $this->assertSame('-$1,234.5B', Money::compact('-1234567890000'));
    }

    public function test_a_compact_negative_reads_like_any_other_negative(): void
    {
        $this->assertSame('-$5.1K', Money::compact('-5150'));
        $this->assertSame('-$999', Money::compact('-999.99'));
        $this->assertSame('$0', Money::compact('-0.5'));
    }

    public function test_a_compact_total_stays_at_its_unit_up_to_the_next(): void
    {
        $this->assertSame('$999.9K', Money::compact('999999.99'));
        $this->assertSame('$1M', Money::compact('1000000'));
    }
}
