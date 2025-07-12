<?php declare(strict_types=1);

namespace EffectPHP\Core;

use Closure;

final class Layer
{
    /** @var Closure(Context): Context */
    private Closure $builder;

    private function __construct(Closure $builder) {
        $this->builder = $builder;
    }

    public static function provides(string $class, object $service): self {
        return self::succeed($class, $service);
    }

    public static function providesFrom(string $class, callable $factory): self {
        return self::from($class, $factory);
    }

    /**
     * Sequential composition – **other** builds first, **$this** builds second.
     */
    public function dependsOn(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            return $this->applyTo($other->applyTo($ctx));
        });
    }

    /**
     * Sequential composition – **this** builds first, **$other** builds second.
     */
    public function usedBy(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            return $other->applyTo($this->applyTo($ctx));
        });
    }

    /** Parallel merge (right‑bias). Both layers see the *same* incoming Context. */
    public function merge(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            $ctxLeft = $this->applyTo($ctx);
            $ctxRight = $other->applyTo($ctx);
            return $ctxLeft->merge($ctxRight); // right‑bias via Context::merge()
        });
    }

    /** Execute layer against a base context. */
    public function applyTo(Context $context): Context {
        return ($this->builder)($context);
    }

    /**
     * Transform resulting Context (rarely needed, but mirrors `Layer.map`).
     *
     * @internal
     */
    protected function map(callable $f): self {
        return new self(
            fn(Context $ctx) => $f($this->applyTo($ctx)),
        );
    }

    /**
     * EffectTS `Layer.succeed` analogue.
     *
     * @internal
     */
    protected static function succeed(string $class, object $service): self {
        return new self(
            static fn(Context $ctx) => $ctx->with($class, $service),
        );
    }

    /**
     * Layer from a factory closure.
     * Factory receives the current Context and returns a service instance.
     *
     * @internal
     */
    protected static function from(string $class, callable $factory): self {
        return new self(
            static fn(Context $ctx) => $ctx->with($class, $factory($ctx)),
        );
    }
}