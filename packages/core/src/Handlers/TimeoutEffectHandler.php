<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Clock\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\Extras\TimeoutEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Exceptions\TimeoutException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;

final class TimeoutEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof TimeoutEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var TimeoutEffect $effect */
        try {
            // Get the Clock service from context
            $clock = $context->getService(Clock::class);
            $startTime = $clock->currentTimeMillis();
            
            $result = $runtime->withContext($context)->run($effect->source);
            
            $elapsed = $clock->currentTimeMillis() - $startTime;
            $timeoutMillis = $effect->duration->toMilliseconds();

            if ($elapsed > $timeoutMillis) {
                return new FailureEffect(Cause::fail(new TimeoutException()));
            }
            
            return $result;
        } catch (ServiceNotFoundException $e) {
            // Fallback to direct microtime if Clock service not available
            $startTime = microtime(true);
            $result = $runtime->withContext($context)->run($effect->source);
            $elapsed = microtime(true) - $startTime;

            if ($elapsed > $effect->duration->toSeconds()) {
                return new FailureEffect(Cause::fail(new TimeoutException()));
            }
            
            return $result;
        }
    }
}