<?php declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\AsyncEffect;
use EffectPHP\Core\Effects\FailEffect;
use EffectPHP\Core\Effects\PureEffect;
use EffectPHP\Core\Effects\ServiceEffect;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use Throwable;

/**
 * Fx is a utility class that provides static methods to create various effects.
 */
final class Fx
{
    private function __construct() {}

    /** @return PureEffect<null> */
    public static function unit(): PureEffect {
        return new PureEffect(null);
    }

    public static function null(): PureEffect {
        return self::unit();
    }

    /**
     * @template T
     * @param T $value
     * @return PureEffect<T>
     */
    public static function succeed(mixed $value): PureEffect {
        return new PureEffect($value);
    }

    /**
     * @template T
     * @param T $value
     * @return PureEffect<T>
     */
    public static function value(mixed $value): PureEffect {
        return self::succeed($value);
    }

    public static function fail(Throwable $e): FailEffect {
        return new FailEffect($e);
    }

    /**
     * @template T
     * @param callable():T $thunk
     * @return SuspendEffect<T>
     */
    public static function suspend(callable $thunk): SuspendEffect {
        return new SuspendEffect($thunk);
    }

    public static function sleep(int $milliseconds): SleepEffect {
        return new SleepEffect($milliseconds);
    }

    public static function service(string $class): ServiceEffect {
        return new ServiceEffect($class);
    }

    public static function async(callable $asyncOperation): AsyncEffect {
        return new AsyncEffect(\Closure::fromCallable($asyncOperation));
    }

    public static function state(): SuspendEffect {
        return new SuspendEffect(static fn(RuntimeState $s) => $s);
    }

    /**
     * Retrieve the currently‑open Scope as an Effect.
     */
    public static function currentScope(): Effect {
        return Fx::state()->map(
            static fn(RuntimeState $state) => $state->scope,
        );
    }
}