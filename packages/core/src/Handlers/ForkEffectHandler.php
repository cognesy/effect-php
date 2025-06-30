<?php

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\Execution\ForkEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;

final class ForkEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool {
        return $effect instanceof ForkEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect {
        /** @var ForkEffect $effect */
        $handle = $runtime->withContext($context)->fork($effect->forked);
        return new SuccessEffect($handle);
    }
}