<?php declare(strict_types=1);

namespace EffectPHP\Core;

use Closure;

final class Layer
{
    /** @var Closure(Context): Context */
    private Closure $build;

    private function __construct(Closure $build) {
        $this->build = $build;
    }

    /** Constant service – EffectTS `Layer.succeed` analogue. */
    public static function succeed(string $class, object $service): self {
        return new self(
            static fn(Context $ctx) => $ctx->with($class, $service)
        );
    }

    /** Layer from a factory closure (may pull dependencies from Context). */
    public static function of(string $class, callable $factory): self {
        return new self(
            static fn(Context $ctx) => $ctx->with($class, $factory($ctx))
        );
    }

    /** Sequential composition – **this** builds first, **$other** builds second. */
    public function compose(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            $ctx1 = $this->apply($ctx);
            return $other->apply($ctx1);
        });
    }

    /** Parallel merge (right‑bias). Both layers see the *same* incoming Context. */
    public function merge(self $other): self {
        return new self(function (Context $ctx) use ($other) {
            $ctxLeft = $this->apply($ctx);
            $ctxRight = $other->apply($ctx);
            return $ctxLeft->merge($ctxRight); // right‑bias via Context::merge()
        });
    }

    /** Transform resulting Context (rarely needed, but mirrors `Layer.map`). */
    public function map(callable $f): self {
        return new self(
            fn(Context $ctx) => $f($this->apply($ctx))
        );
    }

    /** Execute layer against a base context. */
    public function apply(Context $ctx): Context {
        return ($this->build)($ctx);
    }
}