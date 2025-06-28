<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\StringType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * String schema implementation using core Effects
 * 
 * @extends BaseSchema<string, mixed>
 */
final class StringSchema extends BaseSchema
{
    public function __construct(array $annotations = [])
    {
        parent::__construct(new StringType($annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, string>
     */
    public function decode(mixed $input): Effect
    {
        if (!is_string($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('string', $input, [], 'Expected string')
            ]));
        }

        return Eff::succeed($input);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, string>
     */
    public function encode(mixed $input): Effect
    {
        if (!is_string($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('string', $input, [], 'Expected string for encoding')
            ]));
        }

        return Eff::succeed($input);
    }

    /**
     * Override annotate to handle StringSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new StringSchema(
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}
