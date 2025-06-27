<?php

declare(strict_types=1);

namespace EffectPHP\Core\Exceptions;

use LogicException;

class ServiceNotFoundException extends LogicException
{
    public function __construct(string $serviceTag)
    {
        parent::__construct("🔍 Service not found: {$serviceTag}\n" .
                           "💡 Hint: Use Context::withService() or Layer::fromValue() to provide this service");
    }
}
