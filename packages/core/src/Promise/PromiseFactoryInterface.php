<?php

namespace EffectPHP\Core\Promise;

interface PromiseFactoryInterface
{
    /** Convenience helpers. */
    public function fromCallable(callable $callback): PromiseInterface;

    public function resolved(mixed $value): PromiseInterface;

    public function rejected(\Throwable $reason): PromiseInterface;

    public function pending(): PromiseInterface;
}