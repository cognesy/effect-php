<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\ParallelEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;

final class ParallelEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof ParallelEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var ParallelEffect $effect */
        $results = [];
        foreach ($effect->effects as $currentEffect) {
            $result = $runtime->tryRun($currentEffect, $context);
            if ($result instanceof FailureEffect) {
                return $result;
            }
            // If we get a SuccessEffect, extract the value
            if ($result instanceof SuccessEffect) {
                $results[] = $result->value;
            } else {
                // Something unexpected - this shouldn't happen with tryRun
                throw new \RuntimeException('ParallelEffect: Expected SuccessEffect or FailureEffect from tryRun');
            }
        }
        return new SuccessEffect($results);
    }
}