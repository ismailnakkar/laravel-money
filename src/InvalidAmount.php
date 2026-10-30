<?php

declare(strict_types=1);

namespace ExactMoney;

use InvalidArgumentException;

final class InvalidAmount extends InvalidArgumentException
{
    public function __construct(string $amount)
    {
        parent::__construct("[{$amount}] is not an exact decimal.");
    }
}
