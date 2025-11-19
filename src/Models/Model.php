<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\TypeCaster;
use DateTimeInterface;
use ReflectionClass;

abstract class Model
{
    /**
     * Hydrate a model instance from an associative array.
     *
     * This method attempts to call the model constructor when all required
     * constructor parameters are present in `$data`. When some constructor
     * parameters are missing the model is instantiated without running the
     * constructor and remaining properties are set individually. Values are
     * cast using `TypeCaster::castToProperty`.
     *
     * @param  array<string,mixed> $data Associative array of property => value.
     * @return static              The hydrated model instance.
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $modelConstructor = $reflection->getConstructor();
        $obj = null;

        // If constructor exists and EVERY constructor parameter name is present in $data,
        // call the constructor with the provided values (so required initialization runs).
        // Otherwise instantiate without constructor so missing fields are not set to
        // constructor default values (e.g. 'deleted' => false) when the field was not selected.
        // In other words, if some records were selected and not all of the fields were selected for a model
        // then we don't want to include those fields in the array
        $useConstructor = false;
        if ($modelConstructor && $modelConstructor->getNumberOfParameters() > 0) {
            $allParamsProvided = true;
            foreach ($modelConstructor->getParameters() as $param) {
                if (!array_key_exists($param->getName(), $data)) {
                    $allParamsProvided = false;
                    break;
                }
            }
            $useConstructor = $allParamsProvided;
        }

        if ($useConstructor && $modelConstructor) {
            $args = [];
            foreach ($modelConstructor->getParameters() as $param) {
                $name = $param->getName();
                $args[] = TypeCaster::castToProperty(static::class, $name, $data[$name]);
            }
            $obj = $reflection->newInstanceArgs($args);
        } else {
            $obj = $reflection->newInstanceWithoutConstructor();
        }

        // Set any remaining properties (skip ones already passed to constructor)
        $modelConstructorParamNames = [];
        if ($modelConstructor) {
            foreach ($modelConstructor->getParameters() as $param) {
                $modelConstructorParamNames[] = $param->getName();
            }
        }

        foreach ($data as $key => $value) {
            // If we did not call the constructor, set any provided property.
            // If we did call the constructor, skip properties that were passed into it.
            if (property_exists($obj, $key) && (!$useConstructor || !in_array($key, $modelConstructorParamNames, true))) {
                $obj->$key = TypeCaster::castToProperty(static::class, $key, $value);
            }
        }

        return $obj;
    }

    /**
     * Convert the model instance into an associative array.
     *
     * When `$fields` is provided only those properties are included in the
     * resulting array. Typed properties that are not initialized are
     * returned as `null`. `DateTimeInterface` values are converted to SQL
     * datetime strings using `TypeCaster::castDate`.
     *
     * @param  string[]            $fields Optional list of properties to include.
     * @return array<string,mixed> Associative array of model data.
     */
    public function toArray(array $fields = []): array
    {
        $arr = [];
        $fieldFilter = empty($fields) ? null : array_flip($fields);

        foreach ((new ReflectionClass($this))->getProperties() as $prop) {
            $name = $prop->getName();

            // If a fields list was provided, skip properties not in it
            if ($fieldFilter !== null && !isset($fieldFilter[$name])) {
                continue;
            }

            // Avoid accessing uninitialized typed properties (will throw)
            if (method_exists($prop, 'isInitialized') && !$prop->isInitialized($this)) {
                $value = null;
            } else {
                $value = $prop->getValue($this);
            }

            if ($value instanceof DateTimeInterface) {
                $value = TypeCaster::castDate($value);
            }

            $arr[$name] = $value;
        }

        return $arr;
    }
}
