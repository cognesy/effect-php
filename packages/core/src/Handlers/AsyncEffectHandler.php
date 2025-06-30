<?php

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\Execution\AsyncEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;

final class AsyncEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof AsyncEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var AsyncEffect $effect */
        // Universal async bridge - works with promises, callbacks, any async model
        return new SuspendEffect($effect, function($resolve, $reject) use ($effect) {
            try {
                $effect->computation->call($resolve, $reject);
            } catch (\Throwable $e) {
                $reject($e);
            }
        });
    }
}