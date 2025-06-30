<?php

namespace EffectPHP\Core\Effects\Traits;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\Extras\FilterEffect;
use EffectPHP\Core\Exceptions\FilterException;

trait HandlesFilters {
    /**
     * Filter values based on predicate
     *
     * @param callable(A): bool $predicate
     * @param ?string $errorMessage
     * @return Effect<R, E|FilterException, A>
     */
    public function filter(callable $predicate, ?string $errorMessage = null): FilterEffect {
        return new FilterEffect($this, $predicate, $errorMessage);
    }

    /**
     * Filter and transform in one step (common pattern)
     *
     * @template B
     * @param callable(A): ?B $mapper Returns null to filter out
     * @return Effect<R, E|FilterException, B>
     */
    public function filterMap(callable $mapper): Effect {
        return $this
            ->map($mapper)
            ->filter(fn($value) => $value !== null, "Value was filtered out")
            ->map(fn($value) => $value)
        ; // Remove null from type
    }
}