<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\TimeoutEffect;
use EffectPHP\Core\Exceptions\TimeoutException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

final class TimeoutEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof TimeoutEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var TimeoutEffect $effect */
        $startTime = microtime(true);
        $result = $runtime->tryRun($effect->source, $context);
        $elapsed = microtime(true) - $startTime;

        if ($elapsed > $effect->duration->toSeconds()) {
            return new FailureEffect(Cause::fail(new TimeoutException()));
        }
        
        return $result;
    }
}