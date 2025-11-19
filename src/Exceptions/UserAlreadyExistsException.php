<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown if a already exists when attempting to create a new user.
 */
class UserAlreadyExistsException extends RuntimeException
{
}
