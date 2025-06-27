<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use ReflectionProperty;

final class PhpDocExtractor implements MetadataExtractorInterface
{
    public function extractFromProperty(ReflectionProperty $property): PropertyMetadataInterface
    {
        $docComment = $property->getDocComment();
        if (!$docComment) {
            return new PropertyMetadata();
        }

        $type = $this->extractVarType($docComment);
        $description = $this->extractDescription($docComment);
        $nullable = $this->isNullable($docComment);
        $constraints = $this->extractConstraints($docComment);

        return new PropertyMetadata(
            type: $this->normalizeType($type),
            nullable: $nullable,
            description: $description,
            constraints: $constraints
        );
    }

    public function canHandle(ReflectionProperty $property): bool
    {
        return (bool) $property->getDocComment();
    }

    public function getPriority(): int
    {
        return 80;
    }

    private function extractVarType(string $docComment): ?string
    {
        if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function extractDescription(string $docComment): ?string
    {
        $lines = explode("\n", $docComment);
        $description = '';

        foreach ($lines as $line) {
            $line = trim($line, " \t*\/");
            if ($line && !str_starts_with($line, '@')) {
                $description .= $line . ' ';
            }
        }

        return trim($description) ?: null;
    }

    private function isNullable(string $docComment): bool
    {
        return str_contains($docComment, '|null') || str_contains($docComment, 'null|');
    }

    private function extractConstraints(string $docComment): array
    {
        $constraints = [];

        // Extract array type information
        if (preg_match('/array<([^>]+)>/', $docComment, $matches)) {
            $constraints['array_item_type'] = trim($matches[1]);
        } elseif (preg_match('/([^\[\]]+)\[\]/', $docComment, $matches)) {
            $constraints['array_item_type'] = trim($matches[1]);
        }

        return $constraints;
    }

    private function normalizeType(?string $type): ?string
    {
        if (!$type) {
            return null;
        }

        // Clean up union types and nullables
        $type = str_replace(['|null', 'null|'], '', $type);
        $type = trim($type, '|');

        // Check if it's an array type
        if (str_contains($type, '[]') || str_contains($type, 'array<') || str_contains($type, 'Array<')) {
            return 'array';
        }

        return match ($type) {
            'int', 'integer' => 'integer',
            'float', 'double', 'real' => 'number',
            'bool', 'boolean' => 'boolean',
            'string' => 'string',
            default => $type
        };
    }
}
