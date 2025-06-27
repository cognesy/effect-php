<?php

declare(strict_types=1);

namespace EffectPHP\Core\Schedule;

use EffectPHP\Core\Schedule\Nodes\ExponentialNode;
use EffectPHP\Core\Schedule\Nodes\FibonacciNode;
use EffectPHP\Core\Schedule\Nodes\FixedDelayNode;
use EffectPHP\Core\Schedule\Nodes\JitterNode;
use EffectPHP\Core\Schedule\Nodes\LinearNode;
use EffectPHP\Core\Schedule\Nodes\OnceNode;
use EffectPHP\Core\Schedule\Nodes\RepeatNode;
use EffectPHP\Core\Schedule\Nodes\ScheduleNode;
use EffectPHP\Core\Schedule\Nodes\UpToNode;
use EffectPHP\Core\Utils\Duration;

readonly class Schedule
{
    private function __construct(private ScheduleNode $node) {}

    public static function once(): self
    {
        return new self(new OnceNode());
    }

    public static function fixedDelay(Duration $delay): self
    {
        return new self(new FixedDelayNode($delay));
    }

    public static function exponentialBackoff(Duration $base, float $factor = 2.0): self
    {
        return new self(new ExponentialNode($base, $factor));
    }

    public static function fibonacciBackoff(Duration $base): self
    {
        return new self(new FibonacciNode($base));
    }

    public static function linearBackoff(Duration $base): self
    {
        return new self(new LinearNode($base));
    }

    public function upToMaxRetries(int $times): self
    {
        return new self(new RepeatNode($this->node, $times));
    }

    public function upToMaxDuration(Duration $max): self
    {
        return new self(new UpToNode($this->node, $max));
    }

    public function withJitter(float $factor = 0.1): self
    {
        return new self(new JitterNode($this->node, $factor));
    }

    /** @internal */
    public function getNode(): ScheduleNode
    {
        return $this->node;
    }
}