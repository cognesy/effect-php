<?php declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Layer;

interface Effect
{
    public function map(callable $f): Effect;
    public function flatMap(callable $f): Effect;
    public function then(Effect $next): Effect;
    public function provide(Layer $layer): Effect;
}