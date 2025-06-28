<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Runtime\Handlers\AsyncPromiseEffectHandler;
use EffectPHP\Core\Runtime\Handlers\CatchEffectHandler;
use EffectPHP\Core\Runtime\Handlers\EnsuringEffectHandler;
use EffectPHP\Core\Runtime\Handlers\FailureEffectHandler;
use EffectPHP\Core\Runtime\Handlers\FlatMapEffectHandler;
use EffectPHP\Core\Runtime\Handlers\MapEffectHandler;
use EffectPHP\Core\Runtime\Handlers\NeverEffectHandler;
use EffectPHP\Core\Runtime\Handlers\OrElseEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ParallelEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ProvideContextEffectHandler;
use EffectPHP\Core\Runtime\Handlers\RaceEffectHandler;
use EffectPHP\Core\Runtime\Handlers\RetryEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ScopeEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ServiceAccessEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SleepEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SuccessEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SuspendEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SyncEffectHandler;
use EffectPHP\Core\Runtime\Handlers\TimeoutEffectHandler;
use EffectPHP\Core\Contracts\PromiseAdapter;
use LogicException;

/**
 * Registry for effect handlers
 */
final class EffectHandlerRegistry
{
    /** @var EffectHandler[] */
    private array $handlers = [];

    public function __construct(?PromiseAdapter $promiseAdapter = null)
    {
        $this->registerDefaultHandlers($promiseAdapter);
    }

    public function register(EffectHandler $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function getHandler(Effect $effect): EffectHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($effect)) {
                return $handler;
            }
        }

        throw new LogicException('No handler found for effect type: ' . get_class($effect));
    }

    private function registerDefaultHandlers(): void
    {
        $this->register(new AsyncMapEffectHandler());
        $this->register(new CatchEffectHandler());
        $this->register(new EnsuringEffectHandler());
        $this->register(new FailureEffectHandler());
        $this->register(new FlatMapEffectHandler());
        $this->register(new MapEffectHandler());
        $this->register(new NeverEffectHandler());
        $this->register(new OrElseEffectHandler());
        $this->register(new ParallelEffectHandler());
        $this->register(new ProvideContextEffectHandler());
        $this->register(new RaceEffectHandler());
        $this->register(new RetryEffectHandler());
        $this->register(new ScopeEffectHandler());
        $this->register(new ServiceAccessEffectHandler());
        $this->register(new SleepEffectHandler());
        $this->register(new SuccessEffectHandler());
        $this->register(new SuspendEffectHandler());
        $this->register(new SyncEffectHandler());
        $this->register(new TimeoutEffectHandler());
    }
}