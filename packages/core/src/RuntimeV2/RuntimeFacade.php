<?php

namespace EffectPHP\Core\RuntimeV2;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Result\Result;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/** Convenience wrapper around a Runtime instance. */
final class RuntimeFacade
{
    public function __construct(
        private readonly Runtime $runtime,
    ) {}

    public function run(Effect $effect): mixed {
        return $this->runtime->run($effect);
    }

    public function fork(Effect $effect): ExecutionControl {
        return $this->runtime->fork($effect);
    }

    public function promise(Effect $effect): PromiseInterface {
        return new Promise(fn() => $this->run($effect));
    }

    public function callback(Effect $effect, callable $callback): void {
        try {
            $callback(null, $this->run($effect));
        } catch (\Throwable $e) {
            $callback($e, null);
        }
    }

    public function result(Effect $effect): Result {
        try {
            return Result::succeed($this->run($effect));
        } catch (\Throwable $e) {
            return Result::die($e);
        }
    }

    public function engine(): Runtime {
        return $this->runtime;
    }
}