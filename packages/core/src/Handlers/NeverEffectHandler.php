<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\NeverEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Runtime;
use RuntimeException;

/**
 * Handler for NeverEffect - implements permanent suspension
 * 
 * This handler implements the NeverEffect semantics by deliberately never returning
 * a next effect to execute, effectively suspending execution permanently.
 */
final class NeverEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof NeverEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        // NeverEffect represents permanent suspension - we cannot proceed
        // In a real-world implementation, this would:
        // 1. Mark the fiber/execution context as permanently suspended
        // 2. Return control to scheduler/event loop
        // 3. Never resume unless explicitly cancelled
        //
        // For this synchronous runtime, we throw an exception indicating
        // that the effect cannot complete (which is the correct behavior)
        throw new RuntimeException(
            'NeverEffect encountered - this effect never completes. ' .
            'In async runtimes, this would suspend permanently. ' .
            'Consider using timeouts or cancellation for bounded waiting.'
        );
    }
}