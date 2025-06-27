<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\OrElseEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;

final class OrElseEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof OrElseEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var OrElseEffect $effect */
        $primaryResult = $runtime->tryRun($effect->primary, $context);
        return $primaryResult instanceof SuccessEffect
            ? $primaryResult
            : $effect->fallback;
    }
}