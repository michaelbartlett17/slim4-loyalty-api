<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Filter;
use App\Database\Pagination;
use App\Helpers\TypeCaster;
use App\Models\Model;
use InvalidArgumentException;
use PDO;
use ReflectionClass;

/**
 * Base repository providing common CRUD and query helpers.
 *
 * Repository implementations must set the protected properties
 * `$tableName`, `$primaryKey`, and `$modelClass` in the concrete
 * subclass. The class delegates type casting to `TypeCaster` and
 * exposes common operations: `find`, `findAll`, `create`, `save`, and
 * `delete` as well as several protected helper methods for building
 * queries and mapping database rows to model properties.
 *
 * ## Soft Deletes
 * To enable soft deletes, set `$softDeletes = true` in your repository.
 * Your database table MUST have a `deleted_at` column (TIMESTAMP).
 * When soft deletes are enabled, the `delete()` method will set `deleted = 1`
 * instead of removing records.
 *
 * ## Non-Deletable Records
 * Set `$isDeletable = false` to prevent any delete operations on the repository.
 * Attempting to call `delete()` will throw a LogicException.
 */
abstract class Repository
{
    /** @var PDO Database connection used by the repository */
    protected PDO $db;

    /** @var string Primary key property name on the model (camelCase) */
    protected string $primaryKey;

    /** @var string Database table name associated with the repository */
    protected string $tableName;

    /** @var string Fully-qualified model class managed by this repository */
    protected string $modelClass;

    /** @var string[] Fields allowed for insert/update operations */
    protected array $fillableFields = [];

    /** @var bool When true `delete()` performs a soft-delete by setting `deleted_at = date()` */
    protected bool $softDeletes = false;

    /** @var bool When false delete operations are forbidden and will throw */
    protected bool $isDeletable = true;

    public function __construct(PDO $db)
    {
        $this->db = $db;

        if (!isset($this->tableName, $this->primaryKey, $this->modelClass)) {
            throw new \LogicException('Repository must define tableName, primaryKey, and modelClass');
        }

        if (!class_exists($this->modelClass)) {
            throw new \LogicException("Model class {$this->modelClass} does not exist");
        }

        $ref = new ReflectionClass($this->modelClass);
        if (!$ref->hasProperty($this->primaryKey)) {
            throw new \LogicException(
                "Primary key '{$this->primaryKey}' does not exist on model {$this->modelClass}",
            );
        }
    }

    /**
     * Find the first record matching the provided filters.
     *
     * @param  array      $fields     Optional list of model property names to select. When empty all model properties are selected.
     * @param  Filter     ...$filters Zero or more `Filter` objects used to build the WHERE clause.
     * @return Model|null The first record cast to the model instance, or `null` when no row matches.
     */
    public function find(array $fields = [], Filter ...$filters): ?Model
    {
        $fields = $this->resolveFields($fields);
        $sql = 'SELECT ' . $this->fieldsToColumns($fields) . " FROM {$this->tableName}";

        $params = [];
        if ($filters) {
            [$whereSql, $whereParams] = $this->buildWhere($filters);
            $sql .= ' WHERE ' . $whereSql;
            $params = $whereParams;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $data = $this->castRowToModel($data);

        return ($this->modelClass)::fromArray($data);
    }

    /**
     * Find all records matching the provided filters and optional pagination.
     *
     * @param  array            $fields     Optional list of model property names to select. When empty all model properties are selected.
     * @param  Pagination|null  $pagination Optional pagination object which contributes LIMIT/OFFSET and ordering SQL.
     * @param  Filter           ...$filters Zero or more `Filter` objects used to build the WHERE clause.
     * @return array<int,Model> Array of model instances matching the query.
     */
    public function findAll(array $fields = [], ?Pagination $pagination = null, Filter ...$filters): array
    {
        $fields = $this->resolveFields($fields);
        $sql = 'SELECT ' . $this->fieldsToColumns($fields) . " FROM {$this->tableName}";

        $params = [];
        if ($filters) {
            [$whereSql, $whereParams] = $this->buildWhere($filters);
            $sql .= " WHERE {$whereSql}";
            $params = $whereParams;
        }
        if ($pagination) {
            [$paginationSql, $paginationParams] = array_values($pagination->toSql());
            $sql .= " {$paginationSql}";
            $params = array_merge($params, $paginationParams);
        }

        // echo $sql;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($row) => ($this->modelClass)::fromArray($this->castRowToModel($row)), $rows);
    }

    /**
     * Persist a new model to the database.
     *
     * Uses `$fillableFields` if defined on the repository, otherwise it will
     * use all fields returned by `Model::toArray()`.
     *
     * On success the model's primary key property will be populated with the
     * last insert id and the same model instance is returned.
     *
     * @param  Model $model The model to insert.
     * @return Model The same model instance with the primary key populated.
     */
    public function create(Model $model): Model
    {
        $fields = $this->fillableFields ?: array_keys($model->toArray());
        $columns = $this->fieldsToColumns($fields);
        $placeholders = implode(', ', array_map(fn ($f) => ":$f", $fields));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->tableName} ($columns) VALUES ($placeholders)",
        );

        $stmt->execute($this->extractFields($model, $fields));

        $lastId = (int) $this->db->lastInsertId();
        $model->{$this->primaryKey} = $lastId;

        return $model;
    }

    /**
     * Save a model: update if it already exists, otherwise create it.
     *
     * The method determines existence by checking for a primary key value
     * on the model and attempting a `find()` by that primary key.
     *
     * @param  Model $model The model to persist.
     * @return void
     */
    public function save(Model $model): void
    {
        $pk = $this->primaryKey;
        $id = $model->$pk ?? null;

        if ($id && $this->find([], new Filter($this->modelClass, $pk, $id))) {
            $this->update($model);
        } else {
            $this->create($model);
        }
    }

    /**
     * Delete records matching the supplied filters.
     *
     * If the repository has `$softDeletes` enabled this will perform a
     * soft-delete by setting `deleted = 1`; otherwise a SQL `DELETE` is executed.
     *
     * @param  Filter                   ...$filters One or more filters to determine which rows to delete.
     * @throws InvalidArgumentException When no filters are provided.
     * @throws \LogicException          When repository is not deletable (`$isDeletable` is false).
     * @return int                      The number of rows affected.
     */
    public function delete(Filter ...$filters): int
    {
        if (!$filters) {
            throw new InvalidArgumentException('Delete requires at least one filter');
        }

        if (!$this->isDeletable) {
            throw new \LogicException('This record is not deletable');
        }

        $sql = '';

        if ($this->softDeletes) {
            $sql = "UPDATE {$this->tableName} SET `deleted_at` = NOW()";
        } else {
            $sql = "DELETE FROM {$this->tableName}";
        }

        [$whereSql, $params] = $this->buildWhere($filters);
        $sql .= " WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Build a SQL WHERE clause and parameter map from provided filters.
     *
     * Values are cast using the repository's model type information before
     * being added to the parameter array.
     *
     * @param  Filter[]                $filters Array of `Filter` objects.
     * @return array{0:string,1:array} Tuple with SQL string as first element and params array as second.
     */
    protected function buildWhere(array $filters): array
    {
        $sqlParts = [];
        $params = [];

        foreach ($filters as $i => $filter) {
            $isNullOperator = $filter->isNullOperator();
            $value = $this->castValue($filter->property, $filter->value);
            $paramKey = $isNullOperator ? '' : ":f$i";
            $sqlParts[] = $this->camelToSnake($filter->property) . " {$filter->operator} $paramKey";

            if (!$isNullOperator) {
                $params[$paramKey] = $value;
            }
        }

        return [implode(' AND ', $sqlParts), $params];
    }

    /**
     * Update an existing record in the database.
     *
     * Uses `$fillableFields` when present to determine which model properties
     * should be included in the UPDATE statement. Values are cast to the
     * column types using `castValue()` before being bound.
     *
     * @param  Model           $model The model to update.
     * @throws \LogicException when the model cannot be updated since the pk is not set, or null, on the model
     * @return void
     */
    protected function update(Model $model): void
    {
        $fields = $this->fillableFields ?: array_keys($model->toArray());
        $pk = $this->primaryKey;

        if (!isset($model->$pk) || $model->$pk === null) {
            throw new \LogicException(
                'Cannot update model without a primary key value',
            );
        }

        $setParts = [];
        $params = [];
        foreach ($fields as $field) {
            $setParts[] = $this->camelToSnake($field) . " = :$field";
            $params[":$field"] = $this->castValue($field, $model->$field);
        }

        $params[":$pk"] = $model->$pk;
        $sql = "UPDATE {$this->tableName} SET " . implode(', ', $setParts) . ' WHERE ' . $this->camelToSnake($pk) . " = :$pk";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Extract and cast the subset of model fields used for INSERT/UPDATE.
     *
     * Returns an associative array suitable for PDO execute where keys are
     * parameter placeholders (e.g. `:field`) and values are properly cast.
     *
     * @param  Model               $model  The model to extract values from.
     * @param  string[]            $fields List of model properties to extract.
     * @return array<string,mixed> Map of placeholder => value for PDO.
     */
    protected function extractFields(Model $model, array $fields): array
    {
        $data = $model->toArray();

        $result = [];
        foreach ($fields as $field) {
            $result[":$field"] = $this->castValue($field, $data[$field] ?? null);
        }

        return $result;
    }

    /**
     * Validate requested fields against the model and return the valid set.
     *
     * When `$fields` is empty this returns all properties discovered via
     * reflection on the model class. Otherwise only properties that exist on
     * the model are returned (preserves the provided ordering).
     *
     * @param  string[] $fields Optional list of property names to validate.
     * @return string[] Validated list of property names to use in queries.
     */
    protected function resolveFields(array $fields = []): array
    {
        $ref = new ReflectionClass($this->modelClass);
        $modelProperties = array_map(fn ($prop) => $prop->getName(), $ref->getProperties());

        if (empty($fields)) {
            return $modelProperties;
        }

        // Keep only fields that exist on the model
        return array_values(array_intersect($fields, $modelProperties));
    }

    /**
     * Convert model property names to comma-separated column names.
     *
     * Example: ['firstName','lastName'] -> 'first_name, last_name'
     *
     * @param  string[] $fields List of model property names.
     * @return string   Comma-separated column list for SQL.
     */
    protected function fieldsToColumns(array $fields): string
    {
        $columns = array_map(fn ($f) => $this->camelToSnake($f), $fields);
        return implode(', ', $columns);
    }

    /**
     * Convert a camelCase property name into snake_case column name.
     *
     * @param  string $input CamelCase string.
     * @return string snake_case string.
     */
    protected function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', $input));
    }

    /**
     * Cast a value to the model property's declared type using `TypeCaster`.
     *
     * @param  string $property Model property name.
     * @param  mixed  $value    The raw value from the database or input.
     * @return mixed  The value cast to the property's type.
     */
    protected function castValue(string $property, mixed $value): mixed
    {
        return TypeCaster::castToProperty($this->modelClass, $property, $value);
    }

    /**
     * Convert a database row (assoc array) into an array keyed by model
     * property names, casting values as required.
     *
     * Column names are converted from snake_case to camelCase property names.
     *
     * @param  array<string,mixed> $row Database row as returned by PDO.
     * @return array<string,mixed> Array keyed by model property names with cast values.
     */
    protected function castRowToModel(array $row): array
    {
        $result = [];
        foreach ($row as $column => $value) {
            $property = lcfirst(str_replace('_', '', ucwords($column, '_')));
            $result[$property] = $this->castValue($property, $value);
        }
        return $result;
    }
}
