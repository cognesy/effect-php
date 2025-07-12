<?php declare(strict_types=1);

namespace EffectPHP\Core;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\ReserveEffect;

/**
 * Acquire once, release with current Scope.
 */
final class Managed
{
    /**
     * @param Effect $acquire
     * @param callable(mixed):Effect $release
     */
    private function __construct(
        public readonly Effect $acquire,
        private readonly Closure $release,
    ) {}

    /**
     * Creates a Managed resource that acquires once and releases with current Scope.
     *
     * @param callable():Effect $acquire Effect that acquires the resource.
     * @param callable(mixed):void $release Function to release the resource.
     */
    public static function define(callable $acquire, callable $release): self {
        return new self(
            Fx::call($acquire),
            Closure::fromCallable($release),
        );
    }

    /**
     * Returns effect that yields resource & registers release into Scope.
     */
    public function reserve(): Effect {
        return new ReserveEffect($this->acquire, $this->release);
    }
}