<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Internal;

use EffectPHP\Core\Runtime\Scheduler\Scheduler;

/**
 * Runtime configuration
 * 
 * Encapsulates configuration options that affect runtime behavior,
 * such as scheduling strategy, execution flags, and system parameters.
 */
final readonly class RuntimeConfig
{
    public function __construct(
        public ?Scheduler $scheduler = null,
        public bool $enableInterruption = true,
        public bool $enableCooperativeYielding = true,
        public int $maxOperationsBeforeYield = 1024,
        public bool $enableFiberTracking = false
    ) {}

    public function withScheduler(Scheduler $scheduler): self
    {
        return new self(
            scheduler: $scheduler,
            enableInterruption: $this->enableInterruption,
            enableCooperativeYielding: $this->enableCooperativeYielding,
            maxOperationsBeforeYield: $this->maxOperationsBeforeYield,
            enableFiberTracking: $this->enableFiberTracking
        );
    }

    public function withInterruption(bool $enable): self
    {
        return new self(
            scheduler: $this->scheduler,
            enableInterruption: $enable,
            enableCooperativeYielding: $this->enableCooperativeYielding,
            maxOperationsBeforeYield: $this->maxOperationsBeforeYield,
            enableFiberTracking: $this->enableFiberTracking
        );
    }

    public function withCooperativeYielding(bool $enable, int $maxOperations = 1024): self
    {
        return new self(
            scheduler: $this->scheduler,
            enableInterruption: $this->enableInterruption,
            enableCooperativeYielding: $enable,
            maxOperationsBeforeYield: $maxOperations,
            enableFiberTracking: $this->enableFiberTracking
        );
    }

    public function withFiberTracking(bool $enable): self
    {
        return new self(
            scheduler: $this->scheduler,
            enableInterruption: $this->enableInterruption,
            enableCooperativeYielding: $this->enableCooperativeYielding,
            maxOperationsBeforeYield: $this->maxOperationsBeforeYield,
            enableFiberTracking: $enable
        );
    }

    public static function default(): self
    {
        return new self();
    }
}