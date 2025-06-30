<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\Execution\RaceEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use RuntimeException;

final class RaceEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof RaceEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var \EffectPHP\Core\Effects\Execution\RaceEffect $effect */
        foreach ($effect->effects as $currentEffect) {
            $result = $runtime->withContext($context)->run($currentEffect);
            if ($result instanceof SuccessEffect) {
                return $result;
            }
        }
        return new FailureEffect(Cause::fail(new RuntimeException('All effects failed in race')));
    }
}