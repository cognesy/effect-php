<?php

namespace EffectPHP\Swoole;

use EffectPHP\Core\Promise\PromiseFactoryInterface;
use EffectPHP\Core\Promise\PromiseInterface;

class SwoolePromiseAdapter implements PromiseFactoryInterface
{
    private string $state = 'pending'; // pending|fulfilled|rejected
    private mixed $value = null;      // result or exception
    private array $callbacks = [];

    /* ------------------------------------------------------------------ */

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface {
        $next = new self();
        $this->callbacks[] = function () use ($onFulfilled, $onRejected, $next) {
            try {
                if ($this->state === 'fulfilled') {
                    $cb = $onFulfilled ?? static fn($v) => $v;
                    $next->resolved($cb($this->value));
                } else { // rejected
                    if ($onRejected) {
                        $next->resolved($onRejected($this->value));
                    } else {
                        $next->rejected($this->value);
                    }
                }
            } catch (\Throwable $e) {
                $next->rejected($e);
            }
        };
        if ($this->state !== 'pending') {
            $this->runCallbacks();
        }
        return $next;
    }

    /* ------------------------------------------------------------------ */
    /*  Convenient factories                                             */

    public static function fromCallable(callable $callback): PromiseInterface {
        $p = new self();
        // schedule execution on the next Swoole tick (after current I/O cycle)
        Timer::after(0, static function () use ($callback, $p) {
            try {
                $p->resolved($callback());
            } catch (\Throwable $e) {
                $p->rejected($e);
            }
        });
        return $p;
    }

    public static function resolved(mixed $value): PromiseInterface {
        $p = new self();
        $p->tryResolve($value);
        return $p;
    }

    public static function rejected(\Throwable $reason): PromiseInterface {
        $p = new self();
        $p->tryReject($reason);
        return $p;
    }

    /* ------------------------------------------------------------------ */
    /*  Internal state helpers                                            */

    private function tryResolve(mixed $value): void {
        if ($this->state !== 'pending') {
            return;
        }
        $this->state = 'fulfilled';
        $this->value = $value;
        $this->runCallbacks();
    }

    private function tryReject(\Throwable $e): void {
        if ($this->state !== 'pending') {
            return;
        }
        $this->state = 'rejected';
        $this->value = $e;
        $this->runCallbacks();
    }

    private function runCallbacks(): void {
        foreach ($this->callbacks as $cb) {
            $cb();
        }
        $this->callbacks = [];
    }
}