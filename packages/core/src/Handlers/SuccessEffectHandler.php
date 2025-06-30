<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\CatchEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Runtime;

final class SuccessEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SuccessEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SuccessEffect $effect */
        $value = $effect->value;
        
        if ($stack->isEmpty()) {
            return $effect; // Signal completion
        }
        
        $continuation = $stack->pop();
        
        // If continuation is a CatchEffect, it means we're passing through without error
        if ($continuation instanceof CatchEffect) {
            return $effect; // Pass through the success value unchanged
        }
        
        return $continuation($value);
    }
}