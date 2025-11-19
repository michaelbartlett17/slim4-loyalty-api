<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown when, if a user were to redeem points, it would result in a negative balance.
 */
class InsufficientPointsException extends RuntimeException
{
}
