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
 * Collection schema with fluent constraint support
 * 
 * @template T
 * @extends BaseSchema<T[], mixed>
 */
final class CollectionSchema extends BaseSchema
{
    private SchemaInterface $itemSchema;
    private ?int $minSize = null;
    private ?int $maxSize = null;
    private ?int $exactSize = null;

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

        // Check size constraints
        $size = count($input);
        
        if ($this->exactSize !== null && $size !== $this->exactSize) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], "Expected exactly {$this->exactSize} items, got {$size}")
            ]));
        }

        if ($this->minSize !== null && $size < $this->minSize) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], "Expected at least {$this->minSize} items, got {$size}")
            ]));
        }

        if ($this->maxSize !== null && $size > $this->maxSize) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], "Expected at most {$this->maxSize} items, got {$size}")
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

        // Check size constraints (same as decode)
        $size = count($input);
        
        if ($this->exactSize !== null && $size !== $this->exactSize) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], "Expected exactly {$this->exactSize} items, got {$size}")
            ]));
        }

        if ($this->minSize !== null && $size < $this->minSize) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], "Expected at least {$this->minSize} items, got {$size}")
            ]));
        }

        if ($this->maxSize !== null && $size > $this->maxSize) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', $input, [], "Expected at most {$this->maxSize} items, got {$size}")
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
     * Fluent API: Require at least one element (non-empty)
     */
    public function nonEmpty(): self
    {
        return $this->min(1);
    }

    /**
     * Fluent API: Set minimum size constraint
     */
    public function min(int $minSize): self
    {
        $clone = clone $this;
        $clone->minSize = $minSize;
        return $clone->annotate('minItems', $minSize);
    }

    /**
     * Fluent API: Set maximum size constraint
     */
    public function max(int $maxSize): self
    {
        $clone = clone $this;
        $clone->maxSize = $maxSize;
        return $clone->annotate('maxItems', $maxSize);
    }

    /**
     * Fluent API: Set exact size constraint
     */
    public function length(int $exactSize): self
    {
        $clone = clone $this;
        $clone->exactSize = $exactSize;
        return $clone->annotate('exactItems', $exactSize);
    }

    /**
     * Fluent API: Set size range constraint
     */
    public function between(int $minSize, int $maxSize): self
    {
        $clone = clone $this;
        $clone->minSize = $minSize;
        $clone->maxSize = $maxSize;
        return $clone
            ->annotate('minItems', $minSize)
            ->annotate('maxItems', $maxSize);
    }

    /**
     * Override annotate to handle CollectionSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        $clone = new CollectionSchema(
            $this->itemSchema,
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
        
        // Preserve constraints
        $clone->minSize = $this->minSize;
        $clone->maxSize = $this->maxSize;
        $clone->exactSize = $this->exactSize;
        
        return $clone;
    }
}