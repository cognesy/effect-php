<?php declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Traits\Combinators;

final class BindEffect implements Effect
{
    use Combinators;

    public function __construct(
        public readonly Effect $inner,
        /** @var callable(mixed):Effect */
        public readonly Closure $binder,
    ) {}
}
