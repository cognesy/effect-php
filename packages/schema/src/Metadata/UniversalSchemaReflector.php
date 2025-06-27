<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use EffectPHP\Schema\ArraySchema;
use EffectPHP\Schema\BooleanSchema;
use EffectPHP\Schema\NumberSchema;
use EffectPHP\Schema\ObjectSchema;
use EffectPHP\Schema\RefinementSchema;
use EffectPHP\Schema\SchemaInterface;
use EffectPHP\Schema\StringSchema;
use ReflectionClass;
use ReflectionProperty;

final class UniversalSchemaReflector implements SchemaReflectorInterface
{
    private array $extractors = [];

    public function __construct()
    {
        $this->addExtractor(new TypeHintExtractor());
        $this->addExtractor(new PhpDocExtractor());
        $this->addExtractor(new PsalmExtractor());
    }

    public function addExtractor(MetadataExtractorInterface $extractor): SchemaReflectorInterface
    {
        $this->extractors[] = $extractor;

        // Sort by priority (highest first)
        usort($this->extractors, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $this;
    }

    public function fromClass(string $className): SchemaInterface
    {
        $reflection = new ReflectionClass($className);
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $metadata = $this->extractMetadata($property);
            $schema = $this->createSchemaFromMetadata($metadata);

            if (!$metadata->isOptional() && !$metadata->isNullable()) {
                $required[] = $property->getName();
            }

            if ($metadata->isOptional() || $metadata->isNullable()) {
                $schema = $schema->optional();
            }

            $properties[$property->getName()] = $schema;
        }

        return new ObjectSchema($properties, $required);
    }

    public function fromObject(object $object): SchemaInterface
    {
        return $this->fromClass(get_class($object));
    }

    private function extractMetadata(ReflectionProperty $property): PropertyMetadataInterface
    {
        $metadata = new PropertyMetadata();

        foreach ($this->extractors as $extractor) {
            if ($extractor->canHandle($property)) {
                $extractedMetadata = $extractor->extractFromProperty($property);
                $metadata = $metadata->merge($extractedMetadata);
            }
        }

        return $metadata;
    }

    private function createSchemaFromMetadata(PropertyMetadataInterface $metadata): SchemaInterface
    {
        $baseSchema = match ($metadata->getType()) {
            'string' => new StringSchema(),
            'integer' => new NumberSchema(),
            'number' => new NumberSchema(),
            'boolean' => new BooleanSchema(),
            'array' => new ArraySchema(new StringSchema()), // Default to string array
            default => new StringSchema() // Fallback to string
        };

        // Apply constraints from metadata
        $constraints = $metadata->getConstraints();

        if (isset($constraints['minLength'])) {
            $baseSchema = new RefinementSchema(
                $baseSchema,
                fn($value) => is_string($value) && strlen($value) >= $constraints['minLength'],
                "minLength({$constraints['minLength']})"
            );
        }

        if (isset($constraints['maxLength'])) {
            $baseSchema = new RefinementSchema(
                $baseSchema,
                fn($value) => is_string($value) && strlen($value) <= $constraints['maxLength'],
                "maxLength({$constraints['maxLength']})"
            );
        }

        if (isset($constraints['minimum'])) {
            $baseSchema = new RefinementSchema(
                $baseSchema,
                fn($value) => is_numeric($value) && $value >= $constraints['minimum'],
                "minimum({$constraints['minimum']})"
            );
        }

        if (isset($constraints['maximum'])) {
            $baseSchema = new RefinementSchema(
                $baseSchema,
                fn($value) => is_numeric($value) && $value <= $constraints['maximum'],
                "maximum({$constraints['maximum']})"
            );
        }

        if (isset($constraints['pattern'])) {
            $baseSchema = new RefinementSchema(
                $baseSchema,
                fn($value) => is_string($value) && preg_match($constraints['pattern'], $value) === 1,
                "pattern({$constraints['pattern']})"
            );
        }

        // Add description annotation if available
        if ($metadata->getDescription()) {
            $baseSchema = $baseSchema->annotate('description', $metadata->getDescription());
        }

        return $baseSchema;
    }
}
