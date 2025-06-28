<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Schema\AST\TupleType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Tuple schema for validating fixed-length arrays with typed elements
 * Each position has a specific schema, unlike ArraySchema which validates all elements the same
 */
final class TupleSchema extends BaseSchema
{
    /** @var SchemaInterface[] */
    private array $elementSchemas;

    /**
     * @param SchemaInterface[] $elementSchemas
     */
    public function __construct(array $elementSchemas)
    {
        $this->elementSchemas = $elementSchemas;
        
        parent::__construct(new TupleType(
            array_map(fn($schema) => $schema->getAST(), $elementSchemas)
        ));
    }

    public function decode(mixed $input): Effect
    {
        // Must be an array
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', gettype($input), [])
            ]));
        }

        // Must be sequential array (not associative)
        if (!array_is_list($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('sequential array', 'associative array', [])
            ]));
        }

        // Must have exact number of elements
        $expectedCount = count($this->elementSchemas);
        $actualCount = count($input);
        
        if ($actualCount !== $expectedCount) {
            return Eff::fail(new ParseError([
                new TypeIssue("array with {$expectedCount} elements", "array with {$actualCount} elements", [])
            ]));
        }

        // Validate each element with its corresponding schema
        $validatedElements = [];
        $errors = [];

        foreach ($this->elementSchemas as $index => $elementSchema) {
            $element = $input[$index];
            $elementResult = Run::syncResult($elementSchema->decode($element));
            
            if ($elementResult->isFailure()) {
                $errors[] = new TypeIssue('valid element', gettype($element), [(string)$index]);
                continue;
            }

            $validatedElements[] = $elementResult->getValueOrNull() ?? $element;
        }

        if (!empty($errors)) {
            return Eff::fail(new ParseError($errors));
        }

        return Eff::succeed($validatedElements);
    }

    public function encode(mixed $input): Effect
    {
        // Must be an array
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', gettype($input), [])
            ]));
        }

        // Must have exact number of elements
        $expectedCount = count($this->elementSchemas);
        $actualCount = count($input);
        
        if ($actualCount !== $expectedCount) {
            return Eff::fail(new ParseError([
                new TypeIssue("array with {$expectedCount} elements", "array with {$actualCount} elements", [])
            ]));
        }

        // Encode each element with its corresponding schema
        $encodedElements = [];
        $errors = [];

        foreach ($this->elementSchemas as $index => $elementSchema) {
            $element = $input[$index];
            $elementResult = Run::syncResult($elementSchema->encode($element));
            
            if ($elementResult->isFailure()) {
                $errors[] = new TypeIssue('encodable element', gettype($element), [(string)$index]);
                continue;
            }

            $encodedElements[] = $elementResult->getValueOrNull() ?? $element;
        }

        if (!empty($errors)) {
            return Eff::fail(new ParseError($errors));
        }

        return Eff::succeed($encodedElements);
    }

    public function annotate(string $key, mixed $value): SchemaInterface
    {
        $newTupleSchema = new self($this->elementSchemas);
        $newTupleSchema->ast = $this->ast->withAnnotations([$key => $value]);
        return $newTupleSchema;
    }
}