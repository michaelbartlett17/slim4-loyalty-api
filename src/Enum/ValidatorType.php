<?php

declare(strict_types=1);

namespace App\Enum;

enum ValidatorType: string
{
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Double = 'double';
    case String = 'string';
    case Array = 'array';
    case Email = 'email';
}
