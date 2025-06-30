<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\Execution\SyncEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Runtime;
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

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var \EffectPHP\Core\Effects\Execution\SyncEffect $effect */
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