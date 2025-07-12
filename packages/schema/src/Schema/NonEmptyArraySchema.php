<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Schema\AST\NonEmptyArrayType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Non-empty array schema that validates arrays with at least one element
 * Extends ArraySchema validation with additional non-empty constraint
 */
final class NonEmptyArraySchema extends BaseSchema
{
    private SchemaInterface $itemSchema;

    public function __construct(SchemaInterface $itemSchema) {
        $this->itemSchema = $itemSchema;

        parent::__construct(new NonEmptyArrayType($itemSchema->getAST()));
    }

    public function decode(mixed $input): Effect {
        // Must be an array
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', gettype($input), []),
            ]));
        }

        // Must not be empty
        if (empty($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('non-empty array', 'empty array', []),
            ]));
        }

        // Validate each item with the item schema
        $validatedItems = [];
        $errors = [];

        foreach ($input as $index => $item) {
            $itemResult = Run::syncResult($this->itemSchema->decode($item));

            if ($itemResult->isFailure()) {
                $errors[] = new TypeIssue('valid item', gettype($item), [(string)$index]);
                continue;
            }

            $validatedItems[$index] = $itemResult->getValueOrNull() ?? $item;
        }

        if (!empty($errors)) {
            return Eff::fail(new ParseError($errors));
        }

        return Eff::succeed($validatedItems);
    }

    public function encode(mixed $input): Effect {
        // Must be an array
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', gettype($input), []),
            ]));
        }

        // Must not be empty
        if (empty($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('non-empty array', 'empty array', []),
            ]));
        }

        // Encode each item with the item schema
        $encodedItems = [];
        $errors = [];

        foreach ($input as $index => $item) {
            $itemResult = Run::syncResult($this->itemSchema->encode($item));

            if ($itemResult->isFailure()) {
                $errors[] = new TypeIssue('encodable item', gettype($item), [(string)$index]);
                continue;
            }

            $encodedItems[$index] = $itemResult->getValueOrNull() ?? $item;
        }

        if (!empty($errors)) {
            return Eff::fail(new ParseError($errors));
        }

        return Eff::succeed($encodedItems);
    }

    public function annotate(string $key, mixed $value): SchemaInterface {
        $newNonEmptyArraySchema = new self($this->itemSchema);
        $newNonEmptyArraySchema->ast = $this->ast->withAnnotations([$key => $value]);
        return $newNonEmptyArraySchema;
    }
}