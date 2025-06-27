<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\BooleanType;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Boolean schema implementation using core Effects
 * 
 * @extends BaseSchema<bool, mixed>
 */
final class BooleanSchema extends BaseSchema
{
    public function __construct(array $annotations = [])
    {
        parent::__construct(new BooleanType($annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, bool>
     */
    public function decode(mixed $input): Effect
    {
        if (!is_bool($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('boolean', $input, [], 'Expected boolean')
            ]));
        }

        return Eff::succeed($input);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, bool>
     */
    public function encode(mixed $input): Effect
    {
        if (!is_bool($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('boolean', $input, [], 'Expected boolean for encoding')
            ]));
        }

        return Eff::succeed($input);
    }

    /**
     * Override annotate to handle BooleanSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new BooleanSchema(
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}
