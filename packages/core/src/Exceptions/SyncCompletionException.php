<?php

namespace EffectPHP\Core\Exceptions;

final class SyncCompletionException extends \Exception
{
    public function __construct(
        public readonly mixed $result,
        public readonly bool $isError = false
    ) {
        parent::__construct($isError ? 'Sync error completion' : 'Sync completion');
    }
}