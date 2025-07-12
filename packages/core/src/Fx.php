<?php declare(strict_types=1);

namespace EffectPHP\Core;

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

    /**
     * @template T
     * @param T $value
     * @return PureEffect<T>
     */
    public static function succeed(mixed $value): PureEffect {
        return self::value($value);
    }

    public static function fail(Throwable $e): FailEffect {
        return new FailEffect($e);
    }

    /**
     * @template T
     * @param T $value
     * @return PureEffect<T>
     */
    public static function value(mixed $value): PureEffect {
        return new PureEffect($value);
    }

    /**
     * @return PureEffect<null>
     */
    public static function null(): PureEffect {
        return new PureEffect(null);
    }

    /**
     * @template T
     * @param callable():T $thunk
     * @return SuspendEffect<T>
     */
    public static function call(callable $thunk): SuspendEffect {
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
}