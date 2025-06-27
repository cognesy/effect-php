<?php

namespace EffectPHP\Core\Utils;

class Fibonacci
{
    private int $currentValue;
    private int $prevFutureValue;

    private function __construct() {
        $this->currentValue = 0;
        $this->prevFutureValue = 1;
    }

    public static function make(int $n = 0): static {
        $instance = new static();
        for ($i = 0; $i < $n; $i++) {
            $instance->next();
        }
        return $instance;
    }

    public function get(): int {
        return $this->currentValue;
    }

    public function next(): int {
        $futureValue = $this->currentValue + $this->prevFutureValue;
        $this->currentValue = $this->prevFutureValue;
        $this->prevFutureValue = $futureValue;
        return $this->currentValue;
    }
}