<?php declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use EffectPHP\Schema\Contracts\PropertyMetadataInterface;

final class PropertyMetadata implements PropertyMetadataInterface
{
    public function __construct(
        private ?string $type = null,
        private bool $nullable = false,
        private bool $optional = false,
        private array $constraints = [],
        private ?string $description = null,
    ) {}

    public function getType(): ?string {
        return $this->type;
    }

    public function isNullable(): bool {
        return $this->nullable;
    }

    public function isOptional(): bool {
        return $this->optional;
    }

    public function getConstraints(): array {
        return $this->constraints;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function merge(PropertyMetadataInterface $other): PropertyMetadataInterface {
        return new self(
            type: $this->type ?? $other->getType(),
            nullable: $this->nullable || $other->isNullable(),
            optional: $this->optional || $other->isOptional(),
            constraints: array_merge($this->constraints, $other->getConstraints()),
            description: $this->description ?? $other->getDescription(),
        );
    }
}
