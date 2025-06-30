<?php

namespace EffectPHP\Revolt;

use Amp\DeferredFuture;
use Amp\Future;
use EffectPHP\Core\Promise\PromiseFactoryInterface;
use Revolt\EventLoop;

class RevoltPromiseAdapter implements PromiseFactoryInterface
{
    public function __construct(private readonly Future $future) {}

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseFactoryInterface {
        $f = $this->future;
        if ($onFulfilled) {
            $f = $f->map($onFulfilled);
        }
        if ($onRejected) {
            $f = $f->catch($onRejected);
        }
        return new self($f);
    }

    public static function fromCallable(callable $callback): PromiseFactoryInterface {
        $deferred = new DeferredFuture();
        EventLoop::queue(static function () use ($callback, $deferred) {
            try {
                $result = $callback();
                $deferred->complete($result instanceof Future ? $result->await() : $result);
            } catch (\Throwable $e) {
                $deferred->error($e);
            }
        });
        return new self($deferred->getFuture());
    }

    public static function resolved(mixed $value): PromiseFactoryInterface { return new self(Future::complete($value)); }

    public static function rejected(\Throwable $reason): PromiseFactoryInterface { return new self(Future::error($reason)); }
}