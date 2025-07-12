<?php declare(strict_types=1);

namespace EffectPHP\Utils\Clock;

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