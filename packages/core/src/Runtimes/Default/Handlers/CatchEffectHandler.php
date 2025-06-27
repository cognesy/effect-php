<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\CatchEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

final class CatchEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof CatchEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var CatchEffect $effect */
        $stack[] = $effect;
        return $effect->source;
    }
}