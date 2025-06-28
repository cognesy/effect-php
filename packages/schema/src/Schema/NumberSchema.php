<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\NumberType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Number schema implementation using core Effects
 * 
 * @extends BaseSchema<float|int, mixed>
 */
final class NumberSchema extends BaseSchema
{
    public function __construct(array $annotations = [])
    {
        parent::__construct(new NumberType($annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, float|int>
     */
    public function decode(mixed $input): Effect
    {
        if (!is_numeric($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('number', $input, [], 'Expected number')
            ]));
        }

        // Convert to appropriate numeric type
        $value = is_int($input) ? $input : (float) $input;
        return Eff::succeed($value);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, float|int>
     */
    public function encode(mixed $input): Effect
    {
        if (!is_numeric($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('number', $input, [], 'Expected number for encoding')
            ]));
        }

        return Eff::succeed($input);
    }

    /**
     * Override annotate to handle NumberSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new NumberSchema(
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}
