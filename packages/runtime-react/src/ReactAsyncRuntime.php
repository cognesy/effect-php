<?php declare(strict_types=1);

namespace EffectPHP\React;

use EffectPHP\Core\Context;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Handlers\FailHandler;
use EffectPHP\Core\Handlers\PureHandler;
use EffectPHP\Core\Handlers\ServiceHandler;
use EffectPHP\Core\Handlers\SuspendHandler;
use EffectPHP\Utils\Clock\Clock;
use EffectPHP\Utils\Clock\SystemClock;
use EffectPHP\Utils\ContinuationStack;
use EffectPHP\Utils\Result\Result;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReactAsyncHandler;

final class ReactAsyncRuntime implements Runtime
{
    /** @var list<EffectHandler> */
    private array $handlers;

    public function __construct(EffectHandler ...$handlers) {
        $this->handlers = $handlers === []
            ? self::defaultHandlers()
            : $handlers;
    }

    // The run method no longer interacts with the loop directly.
    public function run(Effect $program, ?Context $ctx = null): PromiseInterface {
        $ctx ??= new Context();
        if (!$ctx->has(Clock::class)) {
            $ctx = $ctx->with(Clock::class, new SystemClock());
        }

        $deferred = new Deferred();
        $this->execute($program, $ctx, ContinuationStack::empty(), $deferred);

        return $deferred->promise();
    }

    private function execute(Effect $node, Context $context, ContinuationStack $stack, Deferred $deferred): void {
        // ...
    }

    public function runAll(Effect ...$programs): array {
        // ...
    }

    public function withHandlers(EffectHandler ...$handlers): Runtime {
        // TODO: Implement withHandlers() method.
    }

    public function withContext(Context $context): Runtime {
        // TODO: Implement withContext() method.
    }

    public function tryRun(Effect $program): Result {
        // TODO: Implement tryRun() method.
    }

    public function tryRunAll(Effect ...$programs): Result {
        // TODO: Implement tryRunAll() method.
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private static function defaultHandlers(): array {
        return [
            new FailHandler(),
            new PureHandler(),
            new ServiceHandler(),
            new SuspendHandler(),
            new ReactSleepHandler(),
            new ReactAsyncHandler(),
        ];
    }
}