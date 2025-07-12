<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\BindEffect;
use EffectPHP\Core\RuntimeState;

/**
 * Handles the sequencing of effects via flatMap/map (BindEffect).
 *
 * 1. Runs the inner effect first.
 * 2. Pushes the continuation (binder) onto the stack so that,
 *    when the inner effect finally produces a value, the binder
 *    is applied to that value and the resulting effect is handed
 *    back to the runtime loop.
 */
final class BindHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof BindEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        $stack = $state->stack;

        // Push the binder as a continuation to be executed
        // once the inner effect yields its result.
        /** @var BindEffect $node */
        $newStack = $stack->push($node->binder);

        // Hand the inner effect back to the runtime so it
        // can be evaluated next.
        return $state->with(
            stack: $newStack,
            value: $node->inner,
        );
    }
}