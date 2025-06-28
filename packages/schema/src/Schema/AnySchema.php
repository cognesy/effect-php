<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\AnyType;

/**
 * Any schema that accepts any value without validation
 * Equivalent to EffectTS Schema.Any
 */
final class AnySchema extends BaseSchema
{
    public function __construct()
    {
        parent::__construct(new AnyType());
    }

    public function decode(mixed $input): Effect
    {
        // Accept anything without validation
        return Eff::succeed($input);
    }

    public function encode(mixed $input): Effect
    {
        // Accept anything without validation
        return Eff::succeed($input);
    }
}