<?php

namespace EffectPHP\AmPHP;

use Amp\DeferredFuture;
use Amp\Future;
use EffectPHP\Promise\Contracts\PromiseFactoryInterface;
use EffectPHP\Promise\Contracts\PromiseInterface;
use function Amp\async;

class AmpPromiseAdapter implements PromiseFactoryInterface
{
    public function __construct(private readonly Future $future) {}

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface {
        $f = $this->future;
        if ($onFulfilled) {
            $f = $f->map($onFulfilled);
        }
        if ($onRejected) {
            $f = $f->catch($onRejected);
        }
        return new self($f);
    }

    public static function fromCallable(callable $callback): self {
        return new self(async(static function () use ($callback) {
            $result = $callback();
            return $result instanceof Future ? $result->await() : $result;
        }));
    }

    public static function resolved(mixed $value): self {
        return new self(Future::complete($value));
    }

    public static function rejected(\Throwable $reason): self {
        return new self(Future::error($reason));
    }
}