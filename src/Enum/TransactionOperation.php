<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionOperation: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
}
