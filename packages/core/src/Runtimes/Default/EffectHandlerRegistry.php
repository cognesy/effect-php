<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Runtimes\Default\Handlers\AsyncMapEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\CatchEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\EnsuringEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\FailureEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\FlatMapEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\MapEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\NeverEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\OrElseEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\ParallelEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\ProvideContextEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\RaceEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\RetryEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\ScopeEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\ServiceAccessEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\SleepEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\SuccessEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\SuspendEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\SyncEffectHandler;
use EffectPHP\Core\Runtimes\Default\Handlers\TimeoutEffectHandler;
use LogicException;

/**
 * Registry for effect handlers
 */
final class EffectHandlerRegistry
{
    /** @var EffectHandler[] */
    private array $handlers = [];

    public function __construct()
    {
        $this->registerDefaultHandlers();
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