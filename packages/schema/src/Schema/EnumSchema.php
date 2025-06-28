<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\EnumType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Enum schema implementation using core Effects
 * 
 * @template T
 * @extends BaseSchema<T, mixed>
 */
final class EnumSchema extends BaseSchema
{
    private string $enumClass;
    private array $validValues;

    public function __construct(string $enumClass, array $annotations = [])
    {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Class {$enumClass} is not an enum");
        }

        $this->enumClass = $enumClass;
        $this->validValues = array_map(
            fn($case) => $case->value ?? $case->name,
            $enumClass::cases()
        );

        parent::__construct(new EnumType($enumClass, $annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect
    {
        // For backed enums, check the value
        if (method_exists($this->enumClass, 'tryFrom')) {
            try {
                $result = $this->enumClass::tryFrom($input);
                if ($result !== null) {
                    return Eff::succeed($result);
                }
            } catch (\TypeError $e) {
                // Type mismatch, continue to validation error below
            }
        }

        // For unit enums, check by name
        foreach ($this->enumClass::cases() as $case) {
            if ($case->name === $input) {
                return Eff::succeed($case);
            }
        }

        return Eff::fail(new ParseError([
            new TypeIssue(
                $this->enumClass,
                $input,
                [],
                "Expected one of: " . implode(', ', $this->validValues)
            )
        ]));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function encode(mixed $input): Effect
    {
        if (!$input instanceof $this->enumClass) {
            return Eff::fail(new ParseError([
                new TypeIssue($this->enumClass, $input, [], 'Expected enum instance')
            ]));
        }

        // Return the value for backed enums, name for unit enums
        $result = isset($input->value) ? $input->value : $input->name;
        
        return Eff::succeed($result);
    }

    /**
     * Override annotate to handle EnumSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new EnumSchema(
            $this->enumClass,
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}