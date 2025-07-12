<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\AsyncEffect;
use EffectPHP\Core\RuntimeState;

final class NoAsyncHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof AsyncEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        /* @var AsyncEffect $node */
        $asyncOperation = $node->asyncOperation;

        // Execute the operation synchronously and return its result
        return $state->withValue($asyncOperation());
    }
}