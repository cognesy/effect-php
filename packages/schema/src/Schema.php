<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Result\Result;
use EffectPHP\Core\Run;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Schema\AnySchema;
use EffectPHP\Schema\Schema\ArraySchema;
use EffectPHP\Schema\Schema\BooleanSchema;
use EffectPHP\Schema\Schema\CollectionSchema;
use EffectPHP\Schema\Schema\EnumSchema;
use EffectPHP\Schema\Schema\LiteralSchema;
use EffectPHP\Schema\Schema\NonEmptyArraySchema;
use EffectPHP\Schema\Schema\NumberSchema;
use EffectPHP\Schema\Schema\ObjectSchema;
use EffectPHP\Schema\Schema\RecordSchema;
use EffectPHP\Schema\Schema\RefinementSchema;
use EffectPHP\Schema\Schema\StringSchema;
use EffectPHP\Schema\Schema\TransformationSchema;
use EffectPHP\Schema\Schema\TupleSchema;
use EffectPHP\Schema\Schema\UnionSchema;
use EffectPHP\Schema\Schema\DateSchema;
use EffectPHP\Schema\Schema\DateTimeSchema;

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

    public static function date(): SchemaInterface
    {
        return new DateSchema();
    }

    public static function datetime(): SchemaInterface
    {
        return new DateTimeSchema();
    }

    public static function array(SchemaInterface $itemSchema): SchemaInterface
    {
        return new ArraySchema($itemSchema);
    }

    public static function tuple(SchemaInterface ...$elementSchemas): SchemaInterface
    {
        return new TupleSchema($elementSchemas);
    }

    public static function nonEmptyArray(SchemaInterface $itemSchema): SchemaInterface
    {
        return new NonEmptyArraySchema($itemSchema);
    }

    public static function object(array $properties, array $required = []): SchemaInterface
    {
        return new ObjectSchema($properties, $required);
    }

    public static function union(array $schemas): SchemaInterface
    {
        return new UnionSchema($schemas);
    }

    public static function nullOr(SchemaInterface $schema): SchemaInterface
    {
        return self::union([self::literal(null), $schema]);
    }

    public static function nullishOr(SchemaInterface $schema): SchemaInterface
    {
        // In PHP, nullish means null (undefined doesn't exist as a separate type)
        return self::nullOr($schema);
    }

    public static function undefinedOr(SchemaInterface $schema): SchemaInterface
    {
        // In PHP, undefined is equivalent to null
        return self::nullOr($schema);
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

    public static function nonEmptyString(): SchemaInterface
    {
        return self::refine(
            self::string(),
            fn($value) => is_string($value) && $value !== '',
            'nonEmptyString'
        )->annotate('minLength', 1);
    }

    public static function startsWith(SchemaInterface $schema, string $prefix): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && str_starts_with($value, $prefix),
            "startsWith({$prefix})"
        )->annotate('startsWith', $prefix);
    }

    public static function endsWith(SchemaInterface $schema, string $suffix): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && str_ends_with($value, $suffix),
            "endsWith({$suffix})"
        )->annotate('endsWith', $suffix);
    }

    public static function trimmed(SchemaInterface $schema): SchemaInterface
    {
        return self::refine(
            $schema,
            fn($value) => is_string($value) && trim($value) === $value,
            'trimmed'
        )->annotate('trimmed', true);
    }

    public static function record(
        SchemaInterface $keySchema,
        SchemaInterface $valueSchema
    ): SchemaInterface {
        return new RecordSchema($keySchema, $valueSchema);
    }

    public static function any(): SchemaInterface
    {
        return new AnySchema();
    }

    public static function mixed(): SchemaInterface
    {
        return self::union([
            self::string(),
            self::number(),
            self::boolean(),
            self::any()
        ]);
    }

    public static function enum(string $enumClass): SchemaInterface
    {
        return new EnumSchema($enumClass);
    }

    public static function collection(SchemaInterface $itemSchema): CollectionSchema
    {
        return new CollectionSchema($itemSchema);
    }

    public static function collectionOf(string $className): CollectionSchema
    {
        if (enum_exists($className)) {
            return new CollectionSchema(new EnumSchema($className));
        }

        if (class_exists($className)) {
            // For now, create a simple object schema - can be enhanced later
            return new CollectionSchema(self::any());
        }

        throw new \InvalidArgumentException("Class {$className} does not exist");
    }

    // Number refinements following EffectTS patterns

    public static function integer(): SchemaInterface
    {
        return self::refine(
            self::number(),
            fn($value) => is_numeric($value) && $value == floor($value),
            'integer'
        )->annotate('type', 'integer');
    }

    public static function float(): SchemaInterface
    {
        $floatSchema = self::transform(
            self::number(),
            self::number(),
            fn($value) => (float) $value, // Always convert to float
            fn($value) => (float) $value  // Always convert to float
        );
        
        return $floatSchema->annotate('type', 'float');
    }

    public static function positiveInteger(): SchemaInterface
    {
        return self::refine(
            self::integer(),
            fn($value) => is_numeric($value) && $value > 0 && $value == floor($value),
            'positiveInteger'
        )->annotate('minimum', 1);
    }

    public static function nonNegativeInteger(): SchemaInterface
    {
        return self::refine(
            self::integer(),
            fn($value) => is_numeric($value) && $value >= 0 && $value == floor($value),
            'nonNegativeInteger'
        )->annotate('minimum', 0);
    }

    public static function positiveNumber(): SchemaInterface
    {
        return self::refine(
            self::number(),
            fn($value) => is_numeric($value) && $value > 0,
            'positive'
        )->annotate('minimum', 0)->annotate('exclusiveMinimum', true);
    }

    public static function nonNegativeNumber(): SchemaInterface
    {
        return self::refine(
            self::number(),
            fn($value) => is_numeric($value) && $value >= 0,
            'nonNegative'
        )->annotate('minimum', 0);
    }

    public static function percentage(): SchemaInterface
    {
        return self::min(self::max(self::number(), 100), 0)
            ->annotate('description', 'Percentage (0-100)');
    }

    public static function finite(): SchemaInterface
    {
        return self::refine(
            self::number(),
            fn($value) => is_numeric($value) && is_finite($value + 0),
            'finite'
        )->annotate('description', 'Finite number (not NaN or Infinity)');
    }

    // Static helper methods following EffectTS patterns

    /**
     * Decode unknown input synchronously - throws on error
     * 
     * @template A
     * @param SchemaInterface $schema
     * @return callable(mixed): A
     * @throws \Throwable
     */
    public static function decodeUnknownSync(SchemaInterface $schema): callable
    {
        return function (mixed $input) use ($schema) {
            return Run::sync($schema->decode($input));
        };
    }

    /**
     * Decode unknown input returning Either - no exceptions
     * 
     * @template A
     * @param SchemaInterface $schema
     * @return callable(mixed): Result<\Throwable, A>
     */
    public static function decodeUnknownResult(SchemaInterface $schema): callable
    {
        return function (mixed $input) use ($schema): Result {
            return Run::syncResult($schema->decode($input));
        };
    }

    /**
     * Encode value synchronously - throws on error
     * 
     * @template I
     * @param SchemaInterface $schema
     * @return callable(mixed): I
     * @throws \Throwable
     */
    public static function encodeSync(SchemaInterface $schema): callable
    {
        return function (mixed $value) use ($schema) {
            return Run::sync($schema->encode($value));
        };
    }

    /**
     * Encode value returning Result - no exceptions
     * 
     * @template I
     * @param SchemaInterface $schema
     * @return callable(mixed): Result<\Throwable, I>
     */
    public static function encodeResult(SchemaInterface $schema): callable
    {
        return function (mixed $value) use ($schema): Result {
            return Run::syncResult($schema->encode($value));
        };
    }

    /**
     * Test if value is valid - returns boolean
     * 
     * @param SchemaInterface $schema
     * @return callable(mixed): bool
     */
    public static function is(SchemaInterface $schema): callable
    {
        return function (mixed $input) use ($schema): bool {
            return $schema->is($input);
        };
    }

    /**
     * Assert value is valid - throws on invalid
     * 
     * @template A
     * @param SchemaInterface $schema
     * @return callable(mixed): A
     * @throws \Throwable
     */
    public static function asserts(SchemaInterface $schema): callable
    {
        return function (mixed $input) use ($schema) {
            return $schema->assert($input);
        };
    }
}
