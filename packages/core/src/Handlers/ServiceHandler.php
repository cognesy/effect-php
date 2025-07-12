<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\ServiceEffect;
use EffectPHP\Core\RuntimeState;

final class ServiceHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof ServiceEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        $context = $state->context;
        /* @var ServiceEffect $node */
        return $state->withValue($context->get($node->serviceClass));
    }
}