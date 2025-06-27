<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\ServiceAccessEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;

final class ServiceAccessEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof ServiceAccessEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var ServiceAccessEffect $effect */
        try {
            $service = $context->getService($effect->serviceTag);
            return new SuccessEffect($service);
        } catch (ServiceNotFoundException $e) {
            return new FailureEffect(Cause::fail($e));
        }
    }
}