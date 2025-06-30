<?php

namespace EffectPHP\Core\Exceptions;

use EffectPHP\Core\Contracts\Effect;

class UnhandledEffectException extends \Exception
{
    public function __construct(Effect $effect)
    {
        $message = sprintf(
            'Unhandled effect of type %s',
            get_class($effect)
        );
        parent::__construct($message);
    }
}