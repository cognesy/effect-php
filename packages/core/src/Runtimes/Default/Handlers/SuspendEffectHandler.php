<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

final class SuspendEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SuspendEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SuspendEffect $effect */
        $stack[] = $effect->continuation;
        return $effect->source;
    }
}