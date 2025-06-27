<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\CatchEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

final class SuccessEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SuccessEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SuccessEffect $effect */
        $value = $effect->value;
        
        if (empty($stack)) {
            return $effect; // Signal completion
        }
        
        $continuation = array_pop($stack);
        
        // If continuation is a CatchEffect, it means we're passing through without error
        if ($continuation instanceof CatchEffect) {
            return $effect; // Pass through the success value unchanged
        }
        
        return $continuation($value);
    }
}