<?php

declare(strict_types=1);

namespace EffectPHP\Core\Exceptions;

use RuntimeException;

class TimeoutException extends RuntimeException
{
    public function __construct(string $message = "⏰ Operation timed out")
    {
        parent::__construct($message);
    }
}
