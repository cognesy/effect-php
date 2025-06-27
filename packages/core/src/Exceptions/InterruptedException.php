<?php

declare(strict_types=1);

namespace EffectPHP\Core\Exceptions;

use RuntimeException;

class InterruptedException extends RuntimeException
{
    public function __construct(string $message = "🛑 Operation was interrupted")
    {
        parent::__construct($message);
    }
}
