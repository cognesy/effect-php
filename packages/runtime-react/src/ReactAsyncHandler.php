<?php declare(strict_types=1);

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\AsyncEffect;
use EffectPHP\Core\RuntimeState;

final class ReactAsyncHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof AsyncEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        // TODO: actual async execution should happen here

        /* @var AsyncEffect $node */
        return ($node->asyncOperation)();
    }
}