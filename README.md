# laravel-money

Money as an exact decimal: check it, cast it, add it up, print it. For sites whose balances carry up to nine decimals,
where a float turns `0.00007` into `7.0E-5` and a cent-based library cannot hold the amount at all.

An amount is an immutable `ExactMoney\Money`, from the column to the page, and `+` on one fails instead of quietly making
a float.

## Install

Needs PHP 8.4+, `ext-bcmath` and Laravel 13.

```bash
composer require ismailnakkar/laravel-money
```

The namespace is `ExactMoney\`, because `moneyphp/money` already owns `Money\Money`.

SQLite, Laravel's default, has no `DECIMAL`: it stores a float and reads back a float or an int, and `Money` refuses a
float. Run the app and its tests on MariaDB or MySQL: in `phpunit.xml`, change `DB_CONNECTION` and point `DB_DATABASE`
at a test database of its own, in place of `:memory:`.

## Quick start

```php
use App\Models\User;
use ExactMoney\Money;
use Illuminate\Database\Eloquent\Model;

// In the migration.
$table->decimal('balance', 18, 9)->default(0);

// On the model.
class User extends Model
{
    protected function casts(): array
    {
        return ['balance' => Money::class];
    }
}

$credit = Money::of('0.001345500')->percent('7.5'); // 0.000100912
User::whereKey($id)->increment('balance', $credit->value);

$user = User::find($id);
$user->balance->isPositive();                       // true
Money::balance($user->balance);                     // '$0.0001'
```

## The rules

- Cast every money column to `Money::class`. A float then fails on the way in and on the way out, and a `null` stays
  `null`.
- Do the sums on the object. `$a->add($b)` takes the longer scale, so there is no scale to forget, and `+` or a
  collection's `sum()` throws.
- Ask an amount about itself: `isZero()`, never `if ($amount)`. An object is always truthy, so `Money::of('0')` passes an
  `if`, and PHP gives no warning.
- Wrap what a query hands back. `SUM()` skips the cast, so `Money::of($query->sum('balance'))`.
- Cut once, at the end of a calculation.
- Write with a query, not a model, when two requests can race: `whereKey($id)->increment('balance', $amount->value)`. A
  query's `increment()` needs the digits: it refuses an object. Every other query, `update()` and `where()` included,
  takes the object as it is.
- A method that takes an amount also takes a string or an int, so a value from a form or another package needs no
  wrapping. It refuses a float or a bool.
- Comparisons see every digit. To compare at the column's decimals, as `bccomp($a, $b, 5)` did, cut first:
  `$amount->cut(5)->isPositive()`.
- Store `->value` in the cache and in log context. Where `cache.serializable_classes` is a list or `false`, a `Money`
  comes back as an incomplete class. A model is safe: its attributes stay strings. Monolog writes a `Money` as
  `{"ExactMoney\\Money":"1.5"}`.
- An `Attribute` accessor that returns a `Money` needs `->withoutObjectCaching()`, or it goes stale when a column changes.
- A `Money` isn't scalar, so pass `->value` to code that checks `is_scalar()`, such as a form field component. It can't be
  a parameter's default either: make the parameter required or nullable.
- A cast column is `null` on a model that hasn't been read back, if the column's default came from the database.

## Checking

`Money::of()` gives back a `Money` when the amount is an exact decimal, or throws `ExactMoney\InvalidAmount`, an
`InvalidArgumentException`. Every method checks the same way.

```php
use ExactMoney\Money;

Money::of('0.000100912')->value; // '0.000100912'
Money::of(5)->value;             // '5'

Money::of('1.0E-5');             // throws, and so do '+5', '.5', '5.', '1,000' and ''
```

A float or a bool always throws, with or without `strict_types`. `null` is a `TypeError`, so deal with an empty column
first.

## Calculating

Every method hands back a new amount. `->value` is the digits, and `(string)` and JSON give the same.

```php
$a = Money::of('0.10000');

$a->add('0.2')->value;      // '0.30000'
$a->subtract('0.2')->value; // '-0.10000'
$a->multiply(3)->value;     // '0.30000'
$a->divide(3)->value;       // '0.033333333': truncated at nine decimals
$a->divide(3, 2)->value;    // '0.03'

Money::sum(['0.1', 2, Money::of('0.5')])->value; // '2.6'
Money::zero()->value;                            // '0'
```

Compare with `equals()`, `isGreaterThan()`, `isLessThan()`, `isAtLeast()` and `isAtMost()`, or `compare()` for a sort, and
ask `isZero()`, `isPositive()` and `isNegative()`. They see every digit, where a float would not.

## Cutting

`cut()` truncates, so a printed or paid figure is never larger in size than the stored one. It pads to the decimals you
ask for.

```php
Money::of('1234.56789')->cut(2)->value;                                  // '1234.56'
Money::of('1234.56789')->cut(2, \RoundingMode::HalfAwayFromZero)->value; // '1234.57'
Money::of('5')->cut(6)->value;                                           // '5.000000'
```

`percent()` takes a share at nine decimals, truncated. Pass decimals and a mode to change that. It keeps every digit of
the product and cuts once, so a tie cannot round twice.

```php
Money::of('0.001345500')->percent('7.5')->value;                                // '0.000100912'
Money::of('0.00002')->percent('75', 5, \RoundingMode::HalfAwayFromZero)->value; // '0.00002'
```

## Printing

```php
Money::balance('12345.678912345'); // '$12,345.6789'
Money::price('4.999');             // '$4.99'
Money::compact('5150');            // '$5.1K'
```

Each takes a `Money`, a string or an int. `balance()` is for what a member holds or earned, `price()` for a price, a fee or a payment, always at two decimals,
and `compact()` for a headline figure: `K`, `M` or `B` with at most one decimal, or whole units below 1,000. Every
format truncates, and the sign goes before the symbol: `-$1,234.56`. The output is the same in every language: it does
not use Laravel's `Number` or ICU, whose symbol changes by locale (`$US` in French).

`balance()` takes its decimals from the config, or from the call:

```php
Money::balance('12345.67', 0); // '$12,345'
```

Pass them from the call when an admin can change them: a queue worker reads config once.

In Blade, import the class: there is no facade.

```blade
@use('ExactMoney\Money')

{{ Money::balance($user->balance) }}
```

## Validating

`ExactMoney\Rules\Amount` is for a typed amount. It refuses what `numeric` lets through (`1e3`, `+5`, `.5`), and a
negative or a float. Give it the decimals and the top of the column it fills, or the database rounds or refuses the
rest:

```php
use ExactMoney\Rules\Amount;

$request->validate([
    'rate'    => ['required', new Amount(decimals: 6, max: '999999')],
    'balance' => ['required', new Amount(decimals: 5, max: '9999999.99999')],
]);
```

The defaults are `decimals: 9` and `max: '999999999'`. It adds no messages of its own: it uses Laravel's
`validation.decimal`, `validation.min.numeric` and `validation.max.numeric`.

## Configuration

Optional: publish only to change a default.

```bash
php artisan vendor:publish --tag=money-config
```

| Key                | Default | Meaning                                |
|--------------------|---------|----------------------------------------|
| `symbol`           | `$`     | Printed before every amount.           |
| `balance_decimals` | `4`     | The decimals `Money::balance()` shows. |

## What it leaves out

Rounding rules, fees, minimums, CPM and payout scales are your site's policy, so they stay at your call sites. There is
no currency object and no conversion: one site, one currency. For several currencies, use `moneyphp/money`.

## Upgrading

### From 0.1

An amount is now an object, so this is a break. Change the app and the package in one deploy.

1. Cast each money column to `Money::class`, and delete any other cast on it.
2. `Money::of()` returns a `Money`. Where you need the digits, read `->value`.
3. `Money::cut($amount, 2)` is now `Money::of($amount)->cut(2)`, and `Money::percent($amount, $percent)` is
   `Money::of($amount)->percent($percent)`. Both return a `Money`.
4. Replace `bcadd`, `bcsub`, `bcmul`, `bcdiv` and `bccomp` on amounts with `add()`, `subtract()`, `multiply()`, `divide()` and the
   comparisons. They still work in a file without `strict_types`, where PHP turns a `Money` into a string.
5. Find every truth test on an amount: `if ($amount)`, `empty($amount)`, `$amount ?: …` and `! $amount`. An object is
   always true, so each one becomes `isZero()` or `isPositive()`. Comparing with `>` or `==` throws or misreads too.
6. Pass `$amount->value` to a query's `increment()`, `decrement()` and `incrementEach()`.
7. `+`, `array_sum()` and a collection's `sum()` throw on a `Money`: use `add()` and `Money::sum()`.
8. Wrap a query's `sum()` in `Money::of()`, if you want a `Money`.
9. A factory or seeder that sets a cast column to a float now throws: give it a string.
10. `Money::of()` refuses what `bccomp()` let through, such as `.5` and `+5`. Check amounts you stored as text, such as
    settings, before you deploy.
11. Work through [the rules](#the-rules): comparing at a scale, the cache and logs, accessors and `is_scalar()` each need a
    change at the call site, and the suite won't always say so.

`Money::balance()`, `price()`, `compact()` and the `Amount` rule are unchanged.

## Licence

MIT.
