<?php

namespace EffectPHP\Core\Effects\Traits;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\Execution\ParallelEffect;
use EffectPHP\Core\Effects\Execution\RaceEffect;

trait HandlesExecution {
    public function raceWith(Effect ...$competitors): RaceEffect {
        return new RaceEffect([$this, ...$competitors]);
    }

    public function zipWithPar(Effect ...$others): ParallelEffect {
        return new ParallelEffect([$this, ...$others]);
    }

}