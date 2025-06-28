<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Either;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\Internal\RuntimeConfig;

/**
 * Base Runtime implementation
 * 
 * Provides common functionality for all runtime implementations.
 * Concrete runtimes should extend this class and implement the abstract methods.
 * 
 * Based on EffectTS Runtime design patterns.
 */
abstract class BaseRuntime implements Runtime
{
    protected RuntimeConfig $config;
    protected Context $context;

    public function __construct(RuntimeConfig $config, Context $context)
    {
        $this->config = $config;
        $this->context = $context;
    }

    public function withContext(Context $context): static
    {
        return new static($this->config, $context);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConfig(): RuntimeConfig
    {
        return $this->config;
    }

    public function runSafely(Effect $effect): Either
    {
        try {
            $result = $this->run($effect);
            return Either::right($result);
        } catch (\Throwable $e) {
            return Either::left($e);
        }
    }

    /**
     * @psalm-return FailureEffect<\Throwable>|SuccessEffect<mixed>
     */
    public function tryRun(Effect $effect, ?Context $context = null): FailureEffect|SuccessEffect
    {
        try {
            $result = $this->run($effect, $context);
            return new SuccessEffect($result);
        } catch (\Throwable $e) {
            return new FailureEffect(
                Cause::fail($e)
            );
        }
    }

    // Abstract methods that implementations must provide
    abstract public function run(Effect $effect, ?Context $context = null): mixed;
    abstract public function unsafeRun(Effect $effect): mixed;
    abstract public function getName(): string;
}