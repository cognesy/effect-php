<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\ArrayType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Array schema implementation using core Effects
 * 
 * @template T
 * @extends BaseSchema<T[], mixed>
 */
final class ArraySchema extends BaseSchema
{
    private SchemaInterface $itemSchema;

    public function __construct(SchemaInterface $itemSchema, array $annotations = [])
    {
        $this->itemSchema = $itemSchema;
        parent::__construct(new ArrayType($itemSchema->getAST(), $annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, array>
     */
    public function decode(mixed $input): Effect
    {
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], 'Expected array')
            ]));
        }

        // Use Effect composition to validate all items
        $effects = [];
        foreach ($input as $index => $item) {
            $effects[$index] = $this->itemSchema->decode($item);
        }

        // Use core's parallel execution for array validation
        return Eff::allInParallel($effects);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, array>
     */
    public function encode(mixed $input): Effect
    {
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], 'Expected array for encoding')
            ]));
        }

        // Use Effect composition to encode all items
        $effects = [];
        foreach ($input as $index => $item) {
            $effects[$index] = $this->itemSchema->encode($item);
        }

        // Use core's parallel execution for array encoding
        return Eff::allInParallel($effects);
    }

    /**
     * Override annotate to handle ArraySchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new ArraySchema(
            $this->itemSchema,
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}
