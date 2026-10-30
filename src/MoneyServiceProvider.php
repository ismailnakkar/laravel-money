<?php

declare(strict_types=1);

namespace ExactMoney;

use Illuminate\Support\ServiceProvider;

/** @internal */
class MoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/money.php', 'money');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/money.php' => config_path('money.php')], 'money-config');
    }
}
