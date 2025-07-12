<?php declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Context;
use EffectPHP\Utils\Result\Result;

interface Runtime
{
    public function withHandlers(EffectHandler ...$handlers): self;

    public function withContext(Context $context): self;

    public function run(Effect $program): mixed;

    /** @param list<Effect> $programs */
    public function runAll(Effect ...$programs): array;

    public function tryRun(Effect $program): Result;

    /** @param list<Effect> $programs */
    public function tryRunAll(Effect ...$programs): Result;
}
