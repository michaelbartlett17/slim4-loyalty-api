<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * Repository responsible for persisting `User` models.
 *
 * Configures the base `Repository` with the users table name, primary key,
 * and model class. This repository enables soft deletes by setting
 * `$softDeletes = true` and exposes the list of fields that are allowed to
 * be inserted/updated via `$fillableFields`.
 */
class UserRepository extends Repository
{
    protected string $primaryKey = 'id';
    protected string $tableName = 'users';
    protected string $modelClass = User::class;
    protected bool $softDeletes = true;
    protected array $fillableFields = [
        'name',
        'email',
        'pointsBalance',
    ];
}
