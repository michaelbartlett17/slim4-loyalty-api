<?php

declare(strict_types=1);

namespace App\Database;

use App\Enum\ValidatorRule;
use App\Helpers\DataValidator;
use App\Helpers\TypeCaster;
use ReflectionClass;
use ValueError;

/**
 * Represents a single query filter used to build WHERE clauses.
 *
 * A `Filter` ties a model property to a value and an operator, validates
 * that the property exists on the given model class and that the operator
 * is an acceptable SQL comparison operator, and casts the provided value
 * to the property's declared type. The `toSql()` method returns an array
 * with the SQL fragment and parameter map suitable for use with PDO.
 */
class Filter
{
    /**
     * The model property name (camelCase) this filter targets.
     *
     * @var string
     */
    public string $property;

    /**
     * The fully-qualified model class this filter applies to.
     *
     * Setting this property validates that the model actually declares the
     * referenced property and will throw `ValueError` if not.
     *
     * @var string
     */
    public string $modelClass {
        set(string $value) {
            $ref = new ReflectionClass($value);
            if (!$ref->hasProperty($this->property)) {
                throw new ValueError('The property is not a property of the model class.');
            }
            $this->modelClass = $value;
        }
    }

    /**
     * The (casted) value to compare the property against.
     *
     * @var mixed
     */
    public mixed $value;

    /**
     * SQL comparison operator to use for this filter (e.g. '=', '>', '!=').
     *
     * Setting this property validates the operator is one of the supported
     * comparison operators and throws `ValueError` when invalid.
     *
     * @var string
     */
    public string $operator {
        set(string $value) {
            if (DataValidator::validateValue(ValidatorRule::IsOneOf, ['=', '!=', '<>', '>', '<', '>=', '<=', 'is null', 'is not null', 'IS NULL', 'IS NOT NULL'], $value) !== null) {
                throw new ValueError('The operator is not a valid sql comparison operator');
            }
            $this->operator = $value;
        }
    }

    /**
     * Create a new Filter.
     *
     * The constructor validates the property against the provided model
     * class, validates the operator, and casts the supplied value to the
     * model's property type via `TypeCaster::castToProperty`.
     *
     * @param  string     $modelClass Fully-qualified model class name.
     * @param  string     $property   Model property name (camelCase).
     * @param  mixed      $value      Value to compare; will be cast to the property's type.
     * @param  string     $operator   SQL comparison operator; defaults to '='.
     * @throws ValueError When the property does not exist on the model class or operator is invalid.
     */
    public function __construct(
        string $modelClass,
        string $property,
        mixed $value,
        string $operator = '=',
    ) {
        $this->property = $property;
        $this->modelClass = $modelClass;
        $this->operator = $operator;

        $this->value = TypeCaster::castToProperty($modelClass, $property, $value);
    }

    /**
     * Convert this filter into a SQL fragment and parameters suitable for PDO.
     *
     * Returns an associative array with keys `sql` (the SQL snippet) and
     * `params` (an array mapping placeholder to value).
     *
     * @return array{sql:string,params:array<string,mixed>} The SQL fragment and params.
     */
    public function toSql(): array
    {
        $propertyStr = ":{$this->property}";
        $column = strtolower(preg_replace('/[A-Z]/', '_$0', $this->property));
        if ($this->isNullOperator()) {
            $propertyStr = '';
        }
        return [
            'sql'    => "{$column} {$this->operator} {$propertyStr}",
            'params' => !empty($propertyStr) ? [$propertyStr => $this->value] : [],
        ];
    }
    /**
     * Helper function to determine if the operator is 'is null' or 'is not null' (case insensitive)
     * 
     * @return bool true if the operator is 'is null' or 'is not null'
     */
    public function isNullOperator(): bool
    {
        return in_array($this->operator, ['is null', 'is not null', 'IS NULL', 'IS NOT NULL']);
    }
}
