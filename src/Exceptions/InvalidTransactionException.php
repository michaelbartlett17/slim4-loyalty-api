<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown if a transaction if an array of data cannot be cast to a valid \App\Models\Transaction.
 */
class InvalidTransactionException extends RuntimeException
{
}
