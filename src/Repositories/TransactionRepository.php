<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Transaction;

/**
 * Repository for `Transaction` model persistence.
 *
 * Configures the table, primary key, model class, and fillable fields used
 * by the base `Repository` implementation.
 */
class TransactionRepository extends Repository
{
    protected string $primaryKey = 'id';
    protected string $tableName = 'transactions';
    protected string $modelClass = Transaction::class;
    protected bool $isDeletable = false;
    protected array $fillableFields = [
        'userId',
        'operation',
        'description',
        'amount',
    ];
}
