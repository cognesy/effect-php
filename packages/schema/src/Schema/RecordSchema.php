<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Schema\AST\RecordType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Record schema for validating associative arrays (key-value maps)
 * Handles dynamic keys unlike ObjectSchema which has fixed properties
 */
final class RecordSchema extends BaseSchema
{
    private SchemaInterface $keySchema;
    private SchemaInterface $valueSchema;

    public function __construct(
        SchemaInterface $keySchema,
        SchemaInterface $valueSchema,
    ) {
        $this->keySchema = $keySchema;
        $this->valueSchema = $valueSchema;

        parent::__construct(new RecordType(
            $keySchema->getAST(),
            $valueSchema->getAST(),
        ));
    }

    public function decode(mixed $input): Effect {
        // Must be an array
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', gettype($input), []),
            ]));
        }

        // Must be associative array (not sequential), but empty array is ok
        if (!empty($input) && array_is_list($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('associative array', 'sequential array', []),
            ]));
        }

        // Validate each key-value pair
        $validatedPairs = [];
        $errors = [];

        foreach ($input as $key => $value) {
            // Validate key
            $keyResult = Run::syncResult($this->keySchema->decode($key));
            if ($keyResult->isFailure()) {
                $errors[] = new TypeIssue('valid key', gettype($key), [(string)$key]);
                continue;
            }

            // Validate value
            $valueResult = Run::syncResult($this->valueSchema->decode($value));
            if ($valueResult->isFailure()) {
                $errors[] = new TypeIssue('valid value', gettype($value), [(string)$key]);
                continue;
            }

            $validatedPairs[$keyResult->getValueOrNull() ?? $key] =
                $valueResult->getValueOrNull() ?? $value;
        }

        if (!empty($errors)) {
            return Eff::fail(new ParseError($errors));
        }

        return Eff::succeed($validatedPairs);
    }

    public function encode(mixed $input): Effect {
        // Must be an array
        if (!is_array($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('array', gettype($input), []),
            ]));
        }

        // Encode each key-value pair
        $encodedPairs = [];
        $errors = [];

        foreach ($input as $key => $value) {
            // Encode key
            $keyResult = Run::syncResult($this->keySchema->encode($key));
            if ($keyResult->isFailure()) {
                $errors[] = new TypeIssue('encodable key', gettype($key), [(string)$key]);
                continue;
            }

            // Encode value
            $valueResult = Run::syncResult($this->valueSchema->encode($value));
            if ($valueResult->isFailure()) {
                $errors[] = new TypeIssue('encodable value', gettype($value), [(string)$key]);
                continue;
            }

            $encodedPairs[$keyResult->getValueOrNull() ?? $key] =
                $valueResult->getValueOrNull() ?? $value;
        }

        if (!empty($errors)) {
            return Eff::fail(new ParseError($errors));
        }

        return Eff::succeed($encodedPairs);
    }

    public function annotate(string $key, mixed $value): SchemaInterface {
        $newRecordSchema = new self($this->keySchema, $this->valueSchema);
        $newRecordSchema->ast = $this->ast->withAnnotations([$key => $value]);
        return $newRecordSchema;
    }
}