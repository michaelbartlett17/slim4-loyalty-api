<?php

declare(strict_types=1);

namespace App\Enum;

enum DatabaseOrder: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';
    case asc = 'asc';
    case desc = 'desc';
}
