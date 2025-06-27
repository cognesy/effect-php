<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\RaceEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;
use RuntimeException;

final class RaceEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof RaceEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var RaceEffect $effect */
        foreach ($effect->effects as $currentEffect) {
            $result = $runtime->tryRun($currentEffect, $context);
            if ($result instanceof SuccessEffect) {
                return $result;
            }
        }
        return new FailureEffect(Cause::fail(new RuntimeException('All effects failed in race')));
    }
}