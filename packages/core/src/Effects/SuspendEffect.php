<?php declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Traits\Combinators;

/** @template T */
final class SuspendEffect implements Effect
{
    use Combinators;

    /** @param callable():T $computation */
    public function __construct(
        public readonly Closure $computation,
    ) {}
}
