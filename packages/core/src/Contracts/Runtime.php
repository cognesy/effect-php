<?php declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Context;

interface Runtime
{
    public function run(Effect $program, ?Context $ctx = null): mixed;

    /** @param list<Effect> $programs */
    public function runAll(array $programs, ?Context $ctx = null): array;
}
