<?php declare(strict_types=1);

namespace EffectPHP\Schema\Contracts;

interface PropertyMetadataInterface
{
    public function getType(): ?string;

    public function isNullable(): bool;

    public function isOptional(): bool;

    public function getConstraints(): array;

    public function getDescription(): ?string;

    public function merge(PropertyMetadataInterface $other): self;
}
