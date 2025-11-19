<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\ValidatorRule;
use App\Enum\ValidatorType;
use App\Helpers\DataValidator;
use ValueError;

/**
 * Model representing an application user.
 *
 * The class enforces validation on `name`, `email`, and `pointsBalance`
 * using `DataValidator` helpers and throws `ValueError` when validations
 * fail.
 */
class User extends Model
{
    /** @var int User primary key */
    public int $id;

    /**
     * User display name (non-empty, max 255 chars).
     * @var string
     */
    public string $name {
        set(string $value) {
            if (DataValidator::validateValue(ValidatorRule::MinStrLength, 0, $value) !== null) {
                throw new ValueError('Name must be a non-empty string');
            }
            if (DataValidator::validateValue(ValidatorRule::MaxStrLength, 255, $value) !== null) {
                throw new ValueError('Name must not be greater than 255 characters');
            }

            $this->name = $value;
        }
    }

    /**
     * Email address (validated format, max 255 chars).
     * @var string
     */
    public string $email {
        set(string $value) {
            if (!DataValidator::validateType(ValidatorType::Email, $value)) {
                throw new ValueError('Email must be a valid email');
            }
            if (DataValidator::validateValue(ValidatorRule::MaxStrLength, 255, $value) !== null) {
                throw new ValueError('Email must not be greater than 255 characters');
            }

            $this->email = $value;
        }
    }

    /**
     * Points balance for the user (must be >= 0).
     * @var int
     */
    public int $pointsBalance {
        set(int $value) {
            if (DataValidator::validateValue(ValidatorRule::Min, 0, $value) !== null) {
                throw new ValueError('New points balance must be not be negative');
            }

            $this->pointsBalance = $value;
        }
    }

    /** @var bool Soft-delete timestamp */
    public ?string $deletedAt;

    /**
     * Create a new User model.
     *
     * @param int    $id            Primary key (0 for new records).
     * @param string $name          Display name.
     * @param string $email         Email address.
     * @param int    $pointsBalance Initial points (defaults to 0).
     * @param string $deletedAt     Soft-delete timestamp
     */
    public function __construct(
        int $id,
        string $name,
        string $email,
        int $pointsBalance = 0,
        ?string $deletedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->pointsBalance = $pointsBalance;
        $this->deletedAt = $deletedAt;
    }
}
