<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Runtime;

final class SuspendEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SuspendEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SuspendEffect $effect */
        $stack->push($effect->continuation);
        return $effect->source;
    }
}