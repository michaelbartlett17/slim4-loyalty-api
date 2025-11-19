<?php

declare(strict_types=1);

namespace App\Database;

use App\Enum\DatabaseOrder;
use App\Enum\ValidatorRule;
use App\Helpers\DataValidator;
use ReflectionClass;
use ValueError;

/**
 * Represents pagination, ordering and limit/offset information for queries.
 *
 * The `Pagination` object validates that the provided `modelClass` exists
 * and that `orderBy` corresponds to a real property on the model. Limit and
 * offset values are validated to be non-negative (limit >= 1, offset >= 0).
 */
class Pagination
{
    /**
     * Fully-qualified model class this pagination targets.
     * @var string
     */
    public string $modelClass {
        set(string $value) {
            if (!class_exists($value)) {
                throw new ValueError('modelClass does not exist');
            }
            $this->modelClass = $value;
        }
    }

    /**
     * Model property to order by (camelCase). Validated against the model.
     * @var string
     */
    public string $orderBy {
        set(string $value) {
            $ref = new ReflectionClass($this->modelClass);
            if (!$ref->hasProperty($value)) {
                throw new ValueError('The order by parameter is not a property of the model class.');
            }
            $this->orderBy = $value;
        }
    }

    /**
     * Maximum number of records to return. Must be >= 1.
     * @var int
     */
    public int $limit {
        set(int $value) {
            if (DataValidator::validateValue(ValidatorRule::Min, 1, $value) !== null) {
                throw new ValueError('Limit must be greater than or equal to 1');
            }
            $this->limit = $value;
        }
    }

    /**
     * Result offset. Must be >= 0.
     * @var int
     */
    public int $offset {
        set(int $value) {
            if (DataValidator::validateValue(ValidatorRule::Min, 0, $value) !== null) {
                throw new ValueError('Offset must be greater than or equal to 0');
            }
            $this->offset = $value;
        }
    }

    /**
     * Sort order direction.
     * @var DatabaseOrder
     */
    public DatabaseOrder $order;

    /**
     * Create a new Pagination instance.
     *
     * @param string        $modelClass Fully-qualified model class name.
     * @param string        $orderBy    Model property to order by.
     * @param int           $limit      Maximum records to return (>=1).
     * @param int           $offset     Offset for results (>=0).
     * @param DatabaseOrder $order      Direction of ordering (ASC/DESC).
     */
    public function __construct(
        string $modelClass,
        string $orderBy,
        int $limit,
        int $offset,
        DatabaseOrder $order,
    ) {
        $this->modelClass = $modelClass;
        $this->orderBy = $orderBy;
        $this->order = $order;
        $this->limit = $limit;
        $this->offset = $offset;
    }

    /**
     * Convert pagination info into an SQL fragment and parameter map.
     *
     * @return array{sql:string,params:array<string,int>} SQL and params (limit/offset).
     */
    public function toSql(): array
    {
        $orderBy = strtolower(preg_replace('/[A-Z]/', '_$0', $this->orderBy));
        return [
            'sql'    => "ORDER BY $orderBy {$this->order->value} LIMIT :limit OFFSET :offset",
            'params' => [
                ':limit'  => $this->limit,
                ':offset' => $this->offset,
            ],
        ];
    }
}
