<?php

declare(strict_types=1);

namespace ExactMoney\Tests;

use ExactMoney\InvalidAmount;
use ExactMoney\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** On text columns: SQLite has no DECIMAL, and would read back the float Money refuses. */
final class CastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('wallets', static function (Blueprint $table): void {
            $table->id();
            $table->text('balance')->nullable();
        });
    }

    public function test_a_stored_amount_reads_as_a_money(): void
    {
        $wallet = Wallet::query()->create(['balance' => '0.10000']);

        $this->assertInstanceOf(Money::class, $wallet->fresh()?->balance);
        $this->assertSame('0.10000', $wallet->fresh()?->balance->value);
    }

    public function test_an_empty_column_stays_null(): void
    {
        $this->assertNull(Wallet::query()->create(['balance' => null])->fresh()?->balance);
    }

    public function test_a_money_a_string_and_an_int_all_store_their_digits(): void
    {
        foreach ([[Money::of('1.50'), '1.50'], ['2.50', '2.50'], [3, '3']] as [$amount, $stored]) {
            $wallet = Wallet::query()->create(['balance' => $amount]);

            $this->assertSame($stored, $wallet->getAttributes()['balance']);
        }
    }

    public function test_a_float_never_reaches_the_column(): void
    {
        $this->expectException(InvalidAmount::class);

        new Wallet(['balance' => 0.1]);
    }

    public function test_a_float_already_on_the_row_is_refused_on_reading(): void
    {
        $wallet = (new Wallet)->setRawAttributes(['balance' => 0.1]);

        $this->expectException(InvalidAmount::class);

        $wallet->balance;
    }

    public function test_a_model_can_increment_and_decrement_its_amount(): void
    {
        $wallet = Wallet::query()->create(['balance' => '1.50000']);

        $wallet->increment('balance', Money::of('0.25'));
        $this->assertSame('1.75000', $wallet->balance?->value);

        $wallet->decrement('balance', '0.5');
        $this->assertSame('1.25000', $wallet->balance?->value);
    }

    public function test_an_equal_amount_leaves_the_model_clean(): void
    {
        $wallet = Wallet::query()->create(['balance' => '1.50000'])->fresh();

        $wallet?->fill(['balance' => Money::of('1.50000')]);

        $this->assertFalse($wallet?->isDirty());
    }

    public function test_a_model_encodes_its_amount_as_digits(): void
    {
        $this->assertSame('{"balance":"0.10000"}', (new Wallet(['balance' => '0.10000']))->toJson());
    }
}

/** @property Money|null $balance */
final class Wallet extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['balance' => Money::class];
    }
}
