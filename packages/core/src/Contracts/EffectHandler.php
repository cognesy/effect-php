<?php

declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;

/**
 * Interface for handling specific effect types in the runtime
 */
interface EffectHandler
{
    /**
     * Handle a specific effect type
     *
     * @template A Success value type
     * @template E of \Throwable Error type
     * @template R Environment requirements
     *
     * @param Effect<A, E, R> $effect The effect to handle
     * @param ContinuationStack $stack Reference to the continuation stack
     * @param Context $context The current execution context
     * @param Runtime $runtime The runtime instance for recursive calls
     *
     * @return Effect<A, E, R> The next effect to execute
     */
    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect;

    /**
     * Check if this handler can handle the given effect
     */
    public function canHandle(Effect $effect): bool;
}