<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use ReflectionNamedType;
use ReflectionProperty;

final class TypeHintExtractor implements MetadataExtractorInterface
{
    public function extractFromProperty(ReflectionProperty $property): PropertyMetadataInterface
    {
        if (!$property->hasType()) {
            return new PropertyMetadata();
        }

        $type = $property->getType();
        $nullable = $type->allowsNull();
        $optional = $nullable || $property->hasDefaultValue();

        if ($type instanceof ReflectionNamedType) {
            $typeName = $this->normalizeType($type->getName());

            return new PropertyMetadata(
                type: $typeName,
                nullable: $nullable,
                optional: $optional
            );
        }

        return new PropertyMetadata(nullable: $nullable, optional: $optional);
    }

    public function canHandle(ReflectionProperty $property): bool
    {
        return $property->hasType();
    }

    public function getPriority(): int
    {
        return 100; // Highest priority
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'float', 'double' => 'number',
            'bool' => 'boolean',
            default => $type
        };
    }
}
