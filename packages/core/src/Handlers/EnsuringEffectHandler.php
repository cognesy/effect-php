<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\EnsuringEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Throwable;

final class EnsuringEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof EnsuringEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var EnsuringEffect $effect */
        try {
            $result = $runtime->withContext($context)->run($effect->source);
            ($effect->cleanup)();
            return $result;
        } catch (Throwable $e) {
            ($effect->cleanup)();
            throw $e;
        }
    }
}