<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\PureEffect;
use EffectPHP\Core\RuntimeState;

final class PureHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof PureEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        /* @var PureEffect $node */
        return $state->withValue(
            $node->value,
        );
    }
}