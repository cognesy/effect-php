<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\ObjectType;
use EffectPHP\Schema\Parse\MissingIssue;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Object schema implementation using core Effects
 * 
 * @extends BaseSchema<array, mixed>
 */
final class ObjectSchema extends BaseSchema
{
    private array $properties;
    private array $required;

    /**
     * @param array<string, SchemaInterface> $properties
     * @param string[] $required
     */
    public function __construct(array $properties, array $required = [], array $annotations = [])
    {
        $this->properties = $properties;
        $this->required = $required;

        $astProperties = [];
        foreach ($properties as $key => $schema) {
            $astProperties[$key] = $schema->getAST();
        }

        parent::__construct(new ObjectType($astProperties, $required, $annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, array>
     */
    public function decode(mixed $input): Effect
    {
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('object', $input, [], 'Expected object/array')
            ]));
        }

        // Check required properties first
        foreach ($this->required as $key) {
            if (!array_key_exists($key, $input)) {
                return Eff::fail(new ParseError([
                    new MissingIssue([$key], "Required property '{$key}' is missing")
                ]));
            }
        }

        // Use Effect composition to validate all properties in parallel
        $effects = [];
        foreach ($this->properties as $key => $schema) {
            if (array_key_exists($key, $input)) {
                // Map the property validation result to include the key
                $effects[$key] = $schema->decode($input[$key]);
            } elseif (!in_array($key, $this->required)) {
                // Optional property - provide default null
                $effects[$key] = Eff::succeed(null);
            }
        }

        // Use parallel execution for all property validations
        $keys = array_keys($effects);
        return Eff::allInParallel(array_values($effects))->map(function (array $validatedProperties) use ($keys) {
            // Reconstruct associative array with original keys
            $result = array_combine($keys, $validatedProperties);
            // Filter out null values for optional properties that weren't provided
            return array_filter($result, fn($value) => $value !== null);
        });
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, array>
     */
    public function encode(mixed $input): Effect
    {
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('object', $input, [], 'Expected object/array for encoding')
            ]));
        }

        // Use Effect composition to encode all properties in parallel
        $effects = [];
        foreach ($this->properties as $key => $schema) {
            if (array_key_exists($key, $input)) {
                $effects[$key] = $schema->encode($input[$key]);
            }
        }

        // Use parallel execution for all property encodings
        $keys = array_keys($effects);
        return Eff::allInParallel(array_values($effects))->map(function (array $encodedProperties) use ($keys) {
            // Reconstruct associative array with original keys
            return array_combine($keys, $encodedProperties);
        });
    }
}
