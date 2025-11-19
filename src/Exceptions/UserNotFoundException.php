<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown if a user cannot be found.
 */
class UserNotFoundException extends RuntimeException
{
}
