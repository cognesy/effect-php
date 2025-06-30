<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\Execution\AsyncPromiseEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Runtime;
use Throwable;

final class AsyncPromiseEffectHandler implements EffectHandler
{
    public function __construct(
    ) {}

    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof AsyncPromiseEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var AsyncPromiseEffect $effect */
        try {
            $promise = $this->adapter->fromCallable($effect->computation);
            
            if ($effect->errorHandler !== null) {
                $promise = $promise->then(
                    fn($value) => $value,
                    fn(Throwable $error) => throw ($effect->errorHandler)($error)
                );
            }
            
            // For sync execution, wait for promise
            $result = $promise->wait();
            return new SuccessEffect($result);
            
        } catch (Throwable $error) {
            return new FailureEffect(Cause::fail($error));
        }
    }
}