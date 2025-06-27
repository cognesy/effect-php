<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

/**
 * Schema factory following EffectTS patterns
 * 
 * Creates schemas that return Effects for composition
 */
final class Schema
{
    public static function string(): SchemaInterface
    {
        return new StringSchema();
    }

    public static function number(): SchemaInterface
    {
        return new NumberSchema();
    }

    public static function boolean(): SchemaInterface
    {
        return new BooleanSchema();
    }

    public static function literal(mixed $value): SchemaInterface
    {
        return new LiteralSchema($value);
    }

    public static function array(SchemaInterface $itemSchema): SchemaInterface
    {
        return new ArraySchema($itemSchema);
    }

    public static function object(array $properties, array $required = []): SchemaInterface
    {
        return new ObjectSchema($properties, $required);
    }

    public static function union(array $schemas): SchemaInterface
    {
        return new UnionSchema($schemas);
    }

    public static function transform(
        SchemaInterface $from,
        SchemaInterface $to,
        callable $decode,
        callable $encode
    ): SchemaInterface {
        return new TransformationSchema($from, $to, $decode, $encode);
    }

    public static function refine(
        SchemaInterface $schema,
        callable $predicate,
        string $name = 'refinement'
    ): SchemaInterface {
        return new RefinementSchema($schema, $predicate, $name);
    }

    public static function minLength(SchemaInterface $schema, int $min): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && strlen($value) >= $min,
            "minLength({$min})"
        )->annotate('minLength', $min);
    }

    public static function maxLength(SchemaInterface $schema, int $max): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && strlen($value) <= $max,
            "maxLength({$max})"
        )->annotate('maxLength', $max);
    }

    public static function min(SchemaInterface $schema, float $min): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_numeric($value) && $value >= $min,
            "min({$min})"
        )->annotate('minimum', $min);
    }

    public static function max(SchemaInterface $schema, float $max): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_numeric($value) && $value <= $max,
            "max({$max})"
        )->annotate('maximum', $max);
    }

    public static function email(SchemaInterface $schema): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'email'
        )->annotate('format', 'email');
    }

    public static function pattern(SchemaInterface $schema, string $pattern): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && preg_match($pattern, $value) === 1,
            "pattern({$pattern})"
        )->annotate('pattern', $pattern);
    }
}
