<?php

namespace EffectPHP\Swoole;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;
use EffectPHP\Core\Utils\Duration;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Swoole\Coroutine as SwCoroutine;
use Swoole\Coroutine\Channel as SwChannel;

final class SwooleExecutionStrategy implements ExecutionStrategy
{
    public function sleep(Duration $duration): void {
        SwCoroutine::sleep($duration->toSeconds());
    }

    public function suspend(Closure $continuation): mixed {
        $channel = new SwChannel(1);
        ($continuation)(static function (mixed $value = null, ?\Throwable $throwable = null) use ($channel): void {
            $channel->push([$value, $throwable]);
        });
        [$value, $error] = $channel->pop();
        if ($error instanceof \Throwable) {
            throw $error;
        }
        return $value;
    }

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl {
        $resultChannel = new SwChannel(1);
        $cid = SwCoroutine::create(static function () use ($effect, $runtime, $resultChannel): void {
            try {
                $resultChannel->push([$runtime->run($effect), null]);
            } catch (\Throwable $throwable) {
                $resultChannel->push([null, $throwable]);
            }
        });
        return new SwooleExecutionControl(cid: $cid, resultChannel: $resultChannel);
    }

    public function defer(Closure $callback): PromiseInterface {
        return new Promise(function (callable $resolve, callable $reject) use ($callback): void {
            SwCoroutine::create(function () use ($callback, $resolve, $reject): void {
                try {
                    $result = ($callback)();
                    $resolve($result);
                } catch (\Throwable $throwable) {
                    $reject($throwable);
                }
            });
        });
    }

    public function now(): int {
        return (int) (microtime(true) * 1_000);
    }
}
