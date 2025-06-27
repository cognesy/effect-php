<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FlatMapEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;

final class FlatMapEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof FlatMapEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var FlatMapEffect $effect */
        $stack[] = fn($value) => ($effect->chain)($value);
        return $effect->source;
    }
}