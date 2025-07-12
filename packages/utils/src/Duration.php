<?php declare(strict_types=1);

namespace EffectPHP\Utils;

use DateInterval;

readonly class Duration
{
    public const NANOS_PER_SECOND = 1_000_000_000;
    public const MICROS_PER_SECOND = 1_000_000;
    public const MILLIS_PER_SECOND = 1_000;
    public const SECONDS_PER_MINUTE = 60;
    public const SECONDS_PER_HOUR = 3600;
    public const HOURS_PER_DAY = 24;

    private function __construct(
        private int $seconds,
        private int $nanoseconds = 0,
    ) {}

    // CONSTRUCTORS //////////////////////////////////////////////////////////////////

    public static function zero(): self {
        return new self(seconds: 0, nanoseconds: 0);
    }

    public static function microseconds(int $us): self {
        return new self(
            seconds: intdiv($us, self::MICROS_PER_SECOND),
            nanoseconds: ($us % self::MICROS_PER_SECOND) * self::MILLIS_PER_SECOND,
        );
    }

    public static function milliseconds(int $ms): self {
        return new self(
            seconds: intdiv($ms, self::MILLIS_PER_SECOND),
            nanoseconds: ($ms % self::MILLIS_PER_SECOND) * self::MICROS_PER_SECOND,
        );
    }

    public static function seconds(int $seconds): self {
        return new self(seconds: $seconds);
    }

    public static function minutes(int $minutes): self {
        return new self(
            seconds: $minutes * self::SECONDS_PER_MINUTE,
        );
    }

    public static function hours(int $hours): self {
        return new self($hours * self::SECONDS_PER_HOUR);
    }

    public static function days(int $days): self {
        return new self($days * self::HOURS_PER_DAY * self::SECONDS_PER_HOUR);
    }

    // OPERATIONS /////////////////////////////////////////////////////////////////

    public function plus(Duration $other): self {
        $totalNanos = $this->nanoseconds + $other->nanoseconds;
        $carrySeconds = intdiv($totalNanos, self::NANOS_PER_SECOND);
        $remainingNanos = $totalNanos % self::NANOS_PER_SECOND;

        return new self(
            seconds: $this->seconds + $other->seconds + $carrySeconds,
            nanoseconds: $remainingNanos,
        );
    }

    public function times(float $factor): self {
        $totalNanos = ($this->seconds * self::NANOS_PER_SECOND + $this->nanoseconds) * $factor;
        $seconds = (int)($totalNanos / self::NANOS_PER_SECOND);
        $nanos = ((int)$totalNanos) % self::NANOS_PER_SECOND;
        return new self($seconds, $nanos);
    }

    // CONVERSION ////////////////////////////////////////////////////////////////

    public function toSeconds(): int {
        return $this->seconds;
    }

    public function toMilliseconds(): int {
        return $this->seconds * self::MILLIS_PER_SECOND
            + intdiv($this->nanoseconds, self::MICROS_PER_SECOND);
    }

    public function toMicroseconds(): int {
        return $this->seconds * self::MICROS_PER_SECOND
            + intdiv($this->nanoseconds, self::MILLIS_PER_SECOND);
    }

    public function toDateInterval(): DateInterval {
        $totalSeconds = $this->seconds + intdiv($this->nanoseconds, self::NANOS_PER_SECOND);
        $remainingNanos = $this->nanoseconds % self::NANOS_PER_SECOND;

        $interval = new \DateInterval('PT' . $totalSeconds . 'S');
        if ($remainingNanos > 0) {
            $interval->f = $remainingNanos / self::NANOS_PER_SECOND;
        }
        return $interval;
    }

    // ACCESSORS //////////////////////////////////////////////////////////////

    public function isZero(): bool {
        return $this->seconds === 0
            && $this->nanoseconds === 0;
    }
}