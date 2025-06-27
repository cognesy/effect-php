<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Effects\SyncEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;
use Throwable;

/**
 * Handler for SyncEffect - executes synchronous computations with error handling
 */
final class SyncEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SyncEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SyncEffect $effect */
        
        try {
            // Execute the synchronous computation
            $result = ($effect->computation)();
            return new SuccessEffect($result);
        } catch (Throwable $e) {
            // Wrap exceptions in structured cause
            return new FailureEffect(Cause::fail($e));
        }
    }
}