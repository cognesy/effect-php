<?php

namespace EffectPHP\Workerman;

use EffectPHP\Core\Promise\PromiseAdapterInterface;
use EffectPHP\Core\Promise\PromiseInterface;
use EffectPHP\Core\Promise\ReactPromiseInterface;
use React\Promise as ReactPromise;
use Workerman\Timer;
use function React\Promise as ReactPromise;

// reuse React promise tools

class WorkermanPromiseAdapter implements PromiseAdapterInterface
{
    public function __construct(private readonly ReactPromiseInterface $promise) {}

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface {
        return new self($this->promise->then($onFulfilled, $onRejected));
    }

    public static function fromCallback(callable $callback): PromiseAdapterInterface {
        return new self(new ReactPromise\Promise(static function (callable $resolve, callable $reject) use ($callback) {
            // run once in the next tick (≈0ms; minimum timer interval is 1ms)
            Timer::add(0.001, static function () use ($callback, $resolve, $reject) {
                try {
                    $result = $callback();
                    $result instanceof ReactPromiseInterface
                        ? $result->then($resolve, $reject)
                        : $resolve($result);
                } catch (\Throwable $e) {
                    $reject($e);
                }
            }, [], false);
        }));
    }

    public static function resolved(mixed $value): PromiseAdapterInterface { return new self(ReactPromise\resolve($value)); }

    public static function rejected(\Throwable $reason): PromiseAdapterInterface { return new self(ReactPromise\reject($reason)); }
}