<?php

namespace EffectPHP\Core\Effects\Traits;

use EffectPHP\Core\Effects\Extras\ScopeEffect;

trait HandlesResources {
    public function withinScope(callable $scoped): ScopeEffect {
        return new ScopeEffect($scoped);
    }
}