<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\Execution\ParallelEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;

final class ParallelEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof ParallelEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var ParallelEffect $effect */
        $results = [];
        foreach ($effect->effects as $currentEffect) {
            $result = $runtime->withContext($context)->run($currentEffect);
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