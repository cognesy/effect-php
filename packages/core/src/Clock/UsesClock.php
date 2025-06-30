<?php

namespace EffectPHP\Core\Clock;

trait UsesClock {
    protected Clock $clock;

    public function __construct(
        ?Clock $clock = null
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    public function clock(): Clock {
        return $this->clock;
    }
}