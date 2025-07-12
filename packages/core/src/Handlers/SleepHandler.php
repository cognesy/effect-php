<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\RuntimeState;
use EffectPHP\Utils\Clock\Clock;
use EffectPHP\Utils\Duration;

final class SleepHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof SleepEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        $context = $state->context;

        /** @var Clock $clock */
        $clock = $context->get(Clock::class);
        /** @var SleepEffect $node */
        $clock->sleep(Duration::milliseconds($node->milliseconds));

        return $state->withValue(null);
    }
}