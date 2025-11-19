<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enum\ValidatorRule;
use App\Enum\ValidatorType;
use BackedEnum;
use InvalidArgumentException;

/**
 * Utility for validating values against a set of rules.
 *
 * This class provides helpers to validate individual values and arrays of
 * values using a small enum-based rule system (ValidatorRule and ValidatorType).
 * It supports type checking, min/max numeric checks, string length checks,
 * membership checks (is one of), and whether a value can be cast to a given type.
 */
class DataValidator
{
    /**
     * Validate an array of values against a set of rules.
     *
     * The $rules parameter should be an associative array keyed by the same
     * indexes used in $values. Each entry is an array of rules where the keys
     * are ValidatorRule enum values (using ->value for string keys) and the
     * values are the rule arguments.
     *
     * Example:
     * [
     *   0 => [ ValidatorRule::Required->value => true, ValidatorRule::Type->value => ValidatorType::Integer ],
     *   'name' => [ ValidatorRule::Type->value => ValidatorType::String, ValidatorRule::MaxStrLength->value => 50 ]
     * ]
     *
     * @param  array $values Array of values to validate.
     * @param  array $rules  Array of rules keyed by value index.
     * @return array Associative array of errors keyed by the same indexes; empty if no errors.
     */
    public static function validateArrayOfValues(array $values, array $rules): array
    {
        $errors = [];
        foreach ($rules as $index => $currentRules) {
            $value = $values[$index] ?? null;

            $isRequired = !empty($rules[$index][ValidatorRule::Required->value]);

            if ($isRequired && !$value) {
                $errors[$index] = 'Field is required.';
                continue;
            } elseif (!$isRequired && !$value) {
                continue;
            }

            foreach ($currentRules as $ruleName => $rule) {
                $error = self::validateValue(ValidatorRule::from($ruleName), $rule, $value);
                if ($error) {
                    $errors[$index] = $error;
                    break;
                }
            }
        }
        return $errors;
    }

    /**
     * Validate a single value against a specific rule.
     *
     * The $ruleName identifies which ValidatorRule to apply. The $rule argument
     * contains the rule's parameter (for example, a ValidatorType instance for
     * ValidatorRule::Type, an int for Min/Max, or an array for IsOneOf).
     *
     * This method will throw InvalidArgumentException when a rule's argument
     * type is incorrect (for example passing a non-ValidatorType to Type).
     *
     * @param  ValidatorRule            $ruleName The rule to apply.
     * @param  mixed                    $rule     The rule argument/parameter.
     * @param  mixed                    $value    The value to validate.
     * @return string|null              Error message when validation fails, otherwise null.
     * @throws InvalidArgumentException When the provided rule argument has an invalid type.
     */
    public static function validateValue(ValidatorRule $ruleName, mixed $rule, mixed $value): ?string
    {
        $ruleStr = $rule;
        if ($rule instanceof BackedEnum) {
            $ruleStr = $rule->value;
        }
        if (($ruleName === ValidatorRule::Type || $ruleName === ValidatorRule::CanCast) && !($rule instanceof ValidatorType)) {
            throw new InvalidArgumentException('When using ValidatorRule::Type or ValidatorRule::CanCast, the rule argument should be an instanceof the ValidatorType enum');
        }
        if (($ruleName === ValidatorRule::Min || $ruleName === ValidatorRule::Max) && !self::validateType(ValidatorType::Integer, $rule)) {
            throw new InvalidArgumentException('When using ValidatorRule::Min or ValidatorRule::Max, the rule argument should be an integer');
        }
        if (($ruleName === ValidatorRule::MinStrLength || $ruleName === ValidatorRule::MaxStrLength) && !self::validateType(ValidatorType::Integer, $rule)) {
            throw new InvalidArgumentException('When using ValidatorRule::MinStrLength or ValidatorRule::MaxStrLength, the rule argument should be an integer');
        }
        if ($ruleName === ValidatorRule::IsOneOf && !is_array($rule)) {
            throw new InvalidArgumentException('When using ValidatorRule::IsOneOf, the rule argument should be an array');
        }
        return match ($ruleName) {
            ValidatorRule::Type         => !self::validateType($rule, $value) ? "The value is not a valid $ruleStr" : null,
            ValidatorRule::Min          => $rule > (int) $value ? "The value should be greater than or equal to $ruleStr" : null,
            ValidatorRule::Max          => $rule < (int) $value ? "The value should be less than or equal to $ruleStr" : null,
            ValidatorRule::MinStrLength => (string) strlen($value) < $rule ? "the length of this value should be greater than $ruleStr" : null,
            ValidatorRule::MaxStrLength => (string) strlen($value) > $rule ? "the length of this value should be less than $ruleStr" : null,
            ValidatorRule::IsOneOf      => !in_array($value, $rule, true) ? 'The value must be one of ' . implode(', ', $rule) : null,
            ValidatorRule::CanCast      => !self::validateCanCast($rule, $value) ? "The value can not be cast to a $ruleStr" : null,
            default                     => null
        };
    }

    /**
     * Check whether a value is of a specific ValidatorType.
     *
     * Supported types: Integer, Boolean, Double, String, Array, Email.
     *
     * @param  ValidatorType $type  The expected type.
     * @param  mixed         $value The value to check.
     * @return bool          True if the value matches the type, false otherwise.
     */
    public static function validateType(ValidatorType $type, mixed $value): bool
    {
        return match ($type) {
            ValidatorType::Integer, ValidatorType::Boolean, ValidatorType::Double, ValidatorType::String, ValidatorType::Array => gettype($value) === $type->value,
            ValidatorType::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            default              => false
        };
    }

    /**
     * Check whether a value can be cast to a given ValidatorType.
     *
     * This is less strict than validateType; it checks whether casting/parsing
     * the value to the target type is possible (e.g. "123" can be cast to Integer).
     *
     * @param  ValidatorType $type  The target type to cast to.
     * @param  mixed         $value The value to test for castability.
     * @return bool          True if the value can be cast to the type, false otherwise.
     */
    public static function validateCanCast(ValidatorType $type, mixed $value): bool
    {
        return match ($type) {
            ValidatorType::Integer => filter_var($value, FILTER_VALIDATE_INT) !== false,
            ValidatorType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) !== false,
            ValidatorType::Double  => filter_var($value, FILTER_VALIDATE_FLOAT) !== false,
            ValidatorType::String  => is_scalar($value) || (is_object($value) && method_exists($value, '__toString')),
            default                => false
        };
    }
}
