<?php

declare(strict_types=1);

namespace App\Helpers;

use BackedEnum;
use DateTime;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Helpers to cast raw values to model property types.
 *
 * `TypeCaster::castToProperty` inspects the reflection type of a property
 * and attempts to cast provided values into that type (int, float, bool,
 * string, and date/datetime). It's used throughout the repository layer to
 * ensure values bound to PDO are of the expected types.
 */
class TypeCaster
{
    /**
     * Cast a value to the declared type of a property on the provided class.
     *
     * If the class does not declare the property the original value is
     * returned unchanged. Enums backed by values are converted to their
     * scalar backing value when a BackedEnum instance is provided.
     *
     * @param  string $class    Fully-qualified class name containing the property.
     * @param  string $property Property name on the class.
     * @param  mixed  $value    Value to cast.
     * @return mixed  Casted value or original value when casting isn't possible.
     */
    public static function castToProperty(string $class, string $property, mixed $value): mixed
    {
        $ref = new ReflectionClass($class);

        if (!$ref->hasProperty($property)) {
            return $value;
        }

        $prop = $ref->getProperty($property);
        $type = $prop->getType()?->getName();

        if ($value === null) {
            return $value;
        }

        if (enum_exists($type, true) && $value instanceof BackedEnum) {
            return $value->value;
        }

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => (bool) $value,
            'string' => (string) $value,
            'DateTime',
            'DateTimeImmutable' => self::castDate($value),
            default             => $value
        };
    }

    /**
     * Cast a DateTime-like value into a SQL datetime string.
     *
     * Accepts `DateTimeInterface` instances or any value accepted by
     * `DateTime`'s constructor and returns a `Y-m-d H:i:s` formatted string.
     *
     * @param  mixed  $value DateTimeInterface or parsable date/time string.
     * @return string Formatted datetime string.
     */
    public static function castDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return (new DateTime($value))->format('Y-m-d H:i:s');
    }
}
