<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\RuntimeState;

final class SuspendHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof SuspendEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        /* @var SuspendEffect $node */
        return $state->withValue(($node->thunk)($state));
    }
}