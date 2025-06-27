<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Layer\Context;

/**
 * Interface for handling specific effect types in the runtime
 */
interface EffectHandler
{
    /**
     * Handle a specific effect type
     *
     * @param Effect $effect The effect to handle
     * @param array $stack Reference to the continuation stack
     * @param Context $context The current execution context
     * @param Runtime $runtime The runtime instance for recursive calls
     * @return Effect The next effect to execute
     */
    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect;

    /**
     * Check if this handler can handle the given effect
     */
    public function canHandle(Effect $effect): bool;
}