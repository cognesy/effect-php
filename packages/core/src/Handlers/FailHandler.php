<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\FailEffect;
use EffectPHP\Core\RuntimeState;

final class FailHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof FailEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        /* @var FailEffect $node */
        throw $node->error;
    }
}