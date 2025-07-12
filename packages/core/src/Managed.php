<?php

namespace EffectPHP\Core;

use Closure;
use EffectPHP\Core\Contracts\Effect;

/** Acquire once, release with current Scope. */
final class Managed
{
    /** @param callable(mixed):Effect $release */
    private function __construct(
        public readonly Effect $acquire,
        private readonly Closure $release,
    ) {}

    public static function from(callable $acquire, callable $release): self {
        return new self(
            Fx::suspend($acquire),
            Closure::fromCallable($release),
        );
    }

    /** Returns effect that yields resource & registers release into Scope. */
    public function reserve(): Effect {
        $release = $this->release;
        return $this->acquire->flatMap(
            static function (mixed $res) use ($release): Effect {
                return Scope::current()->map(
                    static function (Scope $scope) use ($res, $release) {
                        $scope->add(fn() => ($release)($res));
                        return $res;
                    },
                );
            },
        );
    }
}