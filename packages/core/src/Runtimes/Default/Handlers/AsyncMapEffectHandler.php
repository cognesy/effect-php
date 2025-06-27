<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\AsyncMapEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

final class AsyncMapEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof AsyncMapEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var AsyncMapEffect $effect */
        $sourceResult = $runtime->tryRun($effect->source, $context);
        if ($sourceResult instanceof SuccessEffect) {
            $mapped = ($effect->mapper)($sourceResult->value);
            return new SuccessEffect($mapped);
        }
        return $sourceResult;
    }
}