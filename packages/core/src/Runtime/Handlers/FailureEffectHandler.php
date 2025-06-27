<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\CatchEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;

final class FailureEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof FailureEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var FailureEffect $effect */
        $cause = $effect->cause;

        // Look for error handlers in the stack
        while (!empty($stack)) {
            $frame = array_pop($stack);
            if ($frame instanceof CatchEffect) {
                if ($this->shouldHandle($cause->toException(), $frame->errorType)) {
                    return ($frame->handler)($cause->toException());
                }
            }
        }

        throw $cause->toException();
    }

    private function shouldHandle(\Throwable $error, string|callable $errorType): bool
    {
        if (is_string($errorType)) {
            return $error instanceof $errorType;
        }

        return $errorType($error);
    }
}