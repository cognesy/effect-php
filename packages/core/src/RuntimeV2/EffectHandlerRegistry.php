<?php

declare(strict_types=1);

namespace EffectPHP\Core\RuntimeV2;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Exceptions\UnhandledEffectException;
use EffectPHP\Core\Handlers\AsyncEffectHandler;
use EffectPHP\Core\Handlers\FailureEffectHandler;
use EffectPHP\Core\Handlers\FlatMapEffectHandler;
use EffectPHP\Core\Handlers\ForkEffectHandler;
use EffectPHP\Core\Handlers\MapEffectHandler;
use EffectPHP\Core\Handlers\SleepEffectHandler;
use EffectPHP\Core\Handlers\SuccessEffectHandler;
use EffectPHP\Core\Handlers\TimeoutEffectHandler;

final class EffectHandlerRegistry
{
    private array $handlers = [];

    public static function createUniversal(): self {
        $registry = new self();

        // Core effect handlers - universal across all environments
        $registry->register(new SuccessEffectHandler());
        $registry->register(new FailureEffectHandler());
        $registry->register(new MapEffectHandler());
        $registry->register(new FlatMapEffectHandler());

        // Strategy-delegating handlers - environment-agnostic
        $registry->register(new SleepEffectHandler());
        $registry->register(new AsyncEffectHandler());
        $registry->register(new ForkEffectHandler());
        $registry->register(new TimeoutEffectHandler());

        return $registry;
    }

    public function register(EffectHandler $handler): void {
        $this->handlers[] = $handler;
    }

    public function getHandler(Effect $effect): EffectHandler {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($effect)) {
                return $handler;
            }
        }
        throw new UnhandledEffectException($effect);
    }
}