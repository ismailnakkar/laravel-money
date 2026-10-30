# laravel-money

Money as an exact decimal string: check it, cut it, print it. For sites whose balances carry up to nine decimals, where
a float turns `0.00007` into `7.0E-5` and a cent-based library cannot hold the amount at all.

There is no money object. An amount stays a string from the column to the page, and `bcmath` does the sums.

## Install

Needs PHP 8.4+, `ext-bcmath` and Laravel 13.

```bash
composer require ismailnakkar/laravel-money
```

The namespace is `ExactMoney\`, because `moneyphp/money` already owns `Money\Money`.

SQLite, Laravel's default, has no `DECIMAL`: it stores a float and reads back a float or an int. Run the app and its
tests on MariaDB or MySQL.

## The rules

- A money column gets no cast, and no float touches a money path. Reading a `DECIMAL` column on MariaDB or MySQL gives a
  string.
- Add, subtract and compare with `bcadd`, `bcsub` and `bccomp` at `Money::SCALE` (9). This package does not wrap them.
- Cut once, at the end of a calculation, and write the rounding mode at that call.
- Write with the query builder: `increment()` with a string sends a decimal literal, never a `DOUBLE`.

## Checking

`Money::of()` gives back an amount that is an exact decimal, or throws `ExactMoney\InvalidAmount`. Every method here
checks the same way.

```php
use ExactMoney\Money;

Money::of('0.000100912'); // '0.000100912'
Money::of(5);             // '5'

Money::of('1.0E-5');      // throws, and so do '+5', '.5', '5.', '1,000' and ''
```

No method takes a float: one throws, whatever its value, with or without `strict_types`.

## Cutting

`Money::cut()` is `bcround` with a default of its own: it truncates, so a printed or paid figure never exceeds the
stored one. It pads to the decimals you ask for.

```php
use RoundingMode;

Money::cut('1234.56789', 2);                                // '1234.56'
Money::cut('1234.56789', 2, RoundingMode::HalfAwayFromZero); // '1234.57'
Money::cut('5', 6);                                         // '5.000000'
```

`Money::percent()` takes a share. It keeps every digit of the product and cuts once, so a tie cannot round twice.

```php
Money::percent('0.001345500', '7.5');                           // '0.000100912'
Money::percent('0.00002', '75', 5, RoundingMode::HalfAwayFromZero); // '0.00002'
```

## Printing

```php
Money::balance('12345.678912345'); // '$12,345.6789'
Money::price('4.999');             // '$4.99'
Money::compact('5150');            // '$5.1K'
```

`balance()` is for what a member holds or earned, `price()` for a price, a fee or a payment, always at two decimals,
and `compact()` for a headline figure. Every format truncates, and the sign goes before the symbol: `-$1,234.56`. The
output is the same in every language: it does not use `Number` or ICU, whose symbol changes by locale (`$US` in French).

`balance()` takes its decimals from the config, or from the call:

```php
Money::balance('12345.67', 0); // '$12,345'
```

Pass them from the call when an admin can change them: a queue worker reads config once.

## Validating

`ExactMoney\Rules\Amount` is for a typed amount. It refuses what `numeric` lets through (`1e3`, `+5`, `.5`), and a
negative or a float. Give it the decimals and the top of the column it fills, or MariaDB rounds or refuses the rest:

```php
use ExactMoney\Rules\Amount;

'rate'    => ['required', new Amount(decimals: 6, max: '999999')],
'balance' => ['required', new Amount(decimals: 5, max: '9999999.99999')],
```

The defaults are `decimals: 9` and `max: '999999999'`. It adds no messages of its own: it uses Laravel's
`validation.decimal` and `validation.max.numeric`.

## Configuration

```bash
php artisan vendor:publish --tag=money-config
```

| Key                | Default | Meaning                               |
|--------------------|---------|---------------------------------------|
| `symbol`           | `$`     | Printed before every amount.          |
| `balance_decimals` | `4`     | The decimals `Money::balance()` shows. |

## What it leaves out

Rounding rules, fees, minimums, CPM and payout scales are your site's policy, so they stay at your call sites. There is
no cast, no currency object and no conversion: one site, one currency. For several currencies, use `moneyphp/money`.

## Upgrading

0.1.0 is the first release.

## Licence

MIT.
