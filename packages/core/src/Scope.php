<?php

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use SplStack;
use Throwable;

/** Simple LIFO stack of finalizers executed on close(). */
final class Scope
{
    /** @var SplStack<callable():void> */
    private SplStack $finalizers;

    public function __construct() {
        $this->finalizers = new SplStack();
    }

    public function add(callable $fin): void {
        $this->finalizers->push($fin);
    }

    public function close(): void {
        while (!$this->finalizers->isEmpty()) {
            try {
                ($this->finalizers->pop())();
            } catch (Throwable) {
                /* swallow */
            }
        }
    }

    /** Retrieve the currently‑open Scope as an Effect. */
    public static function current(): Effect {
        return Fx::state()->map(static fn(RuntimeState $s) => $s->scope);
    }
}