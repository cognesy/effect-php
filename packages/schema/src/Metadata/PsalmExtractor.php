<?php declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use EffectPHP\Schema\Contracts\MetadataExtractorInterface;
use EffectPHP\Schema\Contracts\PropertyMetadataInterface;
use ReflectionProperty;

final class PsalmExtractor implements MetadataExtractorInterface
{
    public function extractFromProperty(ReflectionProperty $property): PropertyMetadataInterface {
        $docComment = $property->getDocComment();
        if (!$docComment) {
            return new PropertyMetadata();
        }

        $constraints = [];

        // Extract Psalm-specific constraints
        if (preg_match('/@psalm-min\s+([0-9.]+)/', $docComment, $matches)) {
            $constraints['minimum'] = (float)$matches[1];
        }

        if (preg_match('/@psalm-max\s+([0-9.]+)/', $docComment, $matches)) {
            $constraints['maximum'] = (float)$matches[1];
        }

        if (preg_match('/@psalm-min-length\s+([0-9]+)/', $docComment, $matches)) {
            $constraints['minLength'] = (int)$matches[1];
        }

        if (preg_match('/@psalm-max-length\s+([0-9]+)/', $docComment, $matches)) {
            $constraints['maxLength'] = (int)$matches[1];
        }

        if (preg_match('/@psalm-pattern\s+([^\s]+)/', $docComment, $matches)) {
            $constraints['pattern'] = trim($matches[1], '"\'');
        }

        return new PropertyMetadata(constraints: $constraints);
    }

    public function canHandle(ReflectionProperty $property): bool {
        $docComment = $property->getDocComment();
        return $docComment && strpos($docComment, '@psalm-') !== false;
    }

    public function getPriority(): int {
        return 70;
    }
}