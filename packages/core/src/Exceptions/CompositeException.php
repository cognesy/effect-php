<?php

declare(strict_types=1);

namespace EffectPHP\Core\Exceptions;

use RuntimeException;

class CompositeException extends RuntimeException
{
    public function __construct(string $message = "💥 Multiple failures occurred")
    {
        parent::__construct($message);
    }
}