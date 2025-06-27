<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use ReflectionProperty;

interface MetadataExtractorInterface
{
    public function extractFromProperty(ReflectionProperty $property): PropertyMetadataInterface;

    public function canHandle(ReflectionProperty $property): bool;

    public function getPriority(): int;
}
