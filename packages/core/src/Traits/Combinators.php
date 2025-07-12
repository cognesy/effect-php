<?php declare(strict_types=1);

namespace EffectPHP\Core\Traits;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BindEffect;
use EffectPHP\Core\Effects\ProvideEffect;
use EffectPHP\Core\Effects\PureEffect;
use EffectPHP\Core\Layer;

trait Combinators
{
    public function map(callable $f): Effect {
        return new BindEffect($this, static fn(mixed $v) => new PureEffect($f($v)));
    }

    public function flatMap(callable $f): Effect {
        return new BindEffect($this, $f);
    }

    public function then(Effect $next): Effect {
        return $this->flatMap(static fn() => $next);
    }

    public function tap(Effect $next): Effect {
        return $this->flatMap(static fn($value) => $next->map(static fn() => $value));
    }

    public function provide(Layer $layer): Effect {
        return new ProvideEffect($this, $layer);
    }
}

