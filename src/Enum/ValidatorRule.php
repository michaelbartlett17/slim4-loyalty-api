<?php

declare(strict_types=1);

namespace App\Enum;

enum ValidatorRule: string
{
    case Type = 'type';
    case Min = 'min';
    case Max = 'max';
    case MinStrLength = 'minStrLength';
    case MaxStrLength = 'maxStrLength';
    case Required = 'required';
    case IsOneOf = 'isOneOf';
    case CanCast = 'canCast';
}
