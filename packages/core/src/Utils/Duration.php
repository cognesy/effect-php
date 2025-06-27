<?php

declare(strict_types=1);

namespace EffectPHP\Core\Utils;

readonly class Duration
{
    private function __construct(
        private int $seconds,
        private int $nanoseconds = 0
    ) {}

    public static function seconds(int $seconds): self
    {
        return new self($seconds);
    }

    public static function milliseconds(int $ms): self
    {
        return new self(
            intdiv($ms, 1000),
            ($ms % 1000) * 1_000_000
        );
    }

    public static function microseconds(int $us): self
    {
        return new self(
            intdiv($us, 1_000_000),
            ($us % 1_000_000) * 1000
        );
    }

    public static function minutes(int $minutes): self
    {
        return new self($minutes * 60);
    }

    public static function hours(int $hours): self
    {
        return new self($hours * 3600);
    }

    public function toSeconds(): int
    {
        return $this->seconds;
    }

    public function toMilliseconds(): int
    {
        return $this->seconds * 1000 + intdiv($this->nanoseconds, 1_000_000);
    }

    public function toMicroseconds(): int
    {
        return $this->seconds * 1_000_000 + intdiv($this->nanoseconds, 1000);
    }

    public function plus(Duration $other): self
    {
        $totalNanos = $this->nanoseconds + $other->nanoseconds;
        $carrySeconds = intdiv($totalNanos, 1_000_000_000);
        $remainingNanos = $totalNanos % 1_000_000_000;

        return new self(
            $this->seconds + $other->seconds + $carrySeconds,
            $remainingNanos
        );
    }

    public function times(float $factor): self
    {
        $totalNanos = ($this->seconds * 1_000_000_000 + $this->nanoseconds) * $factor;
        $seconds = (int) ($totalNanos / 1_000_000_000);
        $nanos = ((int) $totalNanos) % 1_000_000_000;
        return new self($seconds, $nanos);
    }
}