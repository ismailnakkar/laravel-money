<?php

declare(strict_types=1);

namespace ExactMoney\Tests;

use ExactMoney\MoneyServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [MoneyServiceProvider::class];
    }
}
