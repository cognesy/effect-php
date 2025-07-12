<?php declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Utils\Exceptions\CompositeException;
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
        $errors = [];
        while (!$this->finalizers->isEmpty()) {
            try {
                ($this->finalizers->pop())();
            } catch (Throwable $e) {
                // store errors but continue executing finalizers
                $errors[] = $e;
            }
        }

        if (!empty($errors)) {
            throw new CompositeException($errors);
        }
    }

    /** Retrieve the currently‑open Scope as an Effect. */
    public static function current(): Effect {
        return Fx::state()->map(static fn(RuntimeState $s) => $s->scope);
    }
}