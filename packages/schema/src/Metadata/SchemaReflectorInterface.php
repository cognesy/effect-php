<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Metadata;

use EffectPHP\Schema\SchemaInterface;

interface SchemaReflectorInterface
{
    public function fromClass(string $className): SchemaInterface;

    public function fromObject(object $object): SchemaInterface;

    public function addExtractor(MetadataExtractorInterface $extractor): self;
}
