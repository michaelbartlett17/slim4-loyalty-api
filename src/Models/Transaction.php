<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\TransactionOperation;
use App\Enum\ValidatorRule;
use App\Helpers\DataValidator;
use ValueError;

/**
 * Model representing a point transaction for a user.
 *
 * Includes validations for `amount` and `description` via `DataValidator`.
 */
class Transaction extends Model
{
    /** @var int Transaction primary key */
    public int $id;

    /** @var int ID of the user associated with this transaction */
    public int $userId;

    /** @var TransactionOperation Operation type (Earn or Redeem) */
    public TransactionOperation $operation;

    /**
     * Transaction amount (must be >= 1).
     * @var int
     */
    public int $amount {
        set(int $value) {
            if (DataValidator::validateValue(ValidatorRule::Min, 1, $value) !== null) {
                throw new ValueError('The amount of a transaction must be at least 1');
            }
            $this->amount = $value;
        }
    }

    /**
     * Description for the transaction (non-empty, max 255 chars).
     * @var string
     */
    public string $description {
        set(string $value) {
            if (DataValidator::validateValue(ValidatorRule::MinStrLength, 0, $value) !== null) {
                throw new ValueError('Description must be a non-empty string');
            }
            if (DataValidator::validateValue(ValidatorRule::MaxStrLength, 255, $value) !== null) {
                throw new ValueError('Description must not be greater than 255 characters');
            }
            $this->description = $value;
        }
    }

    /**
     * Create a new Transaction model.
     *
     * @param int                  $id          Primary key (0 for new records).
     * @param int                  $userId      Associated user id.
     * @param TransactionOperation $operation   Earn or Redeem.
     * @param int                  $amount      Transaction amount (>=1).
     * @param string               $description Description text (1-255 characters).
     */
    public function __construct(
        int $id,
        int $userId,
        TransactionOperation $operation,
        int $amount,
        string $description,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->operation = $operation;
        $this->amount = $amount;
        $this->description = $description;
    }
}
