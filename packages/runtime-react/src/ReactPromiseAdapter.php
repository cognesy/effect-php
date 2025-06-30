<?php

namespace EffectPHP\React;

use EffectPHP\Core\Promise\PromiseFactoryInterface;
use EffectPHP\Core\Promise\PromiseInterface;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface as ReactPromiseInterface;
use React\Promise as ReactPromiseHelpers;

class ReactPromiseAdapter implements PromiseFactoryInterface
{
    public function __construct(private readonly ReactPromiseInterface $promise) {}

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface {
        return new self($this->promise->then($onFulfilled, $onRejected));
    }

    public static function fromCallable(callable $callback): self {
        return new self(new ReactPromiseHelpers\Promise(static function (callable $resolve, callable $reject) use ($callback) {
            Loop::defer(static function () use ($callback, $resolve, $reject) {
                try {
                    $result = $callback();
                    $result instanceof ReactPromiseInterface
                        ? $result->then($resolve, $reject)
                        : $resolve($result);
                } catch (\Throwable $e) {
                    $reject($e);
                }
            });
        }));
    }

    public static function resolved(mixed $value): self {
        return new self(ReactPromiseHelpers\resolve($value));
    }

    public static function rejected(\Throwable $reason): self {
        return new self(ReactPromiseHelpers\reject($reason));
    }
}
