<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\ProvideContextEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

final class ProvideContextEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof ProvideContextEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var ProvideContextEffect $effect */
        // Context merge is handled by the runtime, just return the source
        return $effect->source;
    }
}