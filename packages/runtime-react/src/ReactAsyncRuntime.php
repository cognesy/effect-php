<?php declare(strict_types=1);

namespace EffectPHP\Runtime\React;

use EffectPHP\Core\Context;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\BindEffect;
use EffectPHP\Core\Effects\ProvideEffect;
use EffectPHP\Core\Effects\PureEffect;
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
        $this->execute($program, $ctx, new ContinuationStack(), $deferred);

        return $deferred->promise();
    }

    private function execute(Effect $node, Context $ctx, ContinuationStack $cont, Deferred $deferred): void {
        if ($node instanceof ProvideEffect) {
            $prevCtx = $ctx;
            $ctx = $node->layer->applyTo($ctx);
            $newNode = new BindEffect(
                $node->inner,
                function (mixed $val) use (&$ctx, $prevCtx): Effect {
                    $ctx = $prevCtx;
                    return new PureEffect($val);
                },
            );
            $this->execute($newNode, $ctx, $cont, $deferred);
            return;
        }

        if ($node instanceof BindEffect) {
            $cont->push($node->binder);
            $this->execute($node->inner, $ctx, $cont, $deferred);
            return;
        }

        foreach ($this->handlers as $h) {
            if ($h->supports($node)) {
                $result = $h->handle($node, static fn() => null, $ctx);
                if ($result instanceof PromiseInterface) {
                    $result->then(
                        function ($value) use ($cont, $ctx, $deferred) {
                            if ($cont->isEmpty()) {
                                $deferred->resolve($value);
                            } else {
                                $nextNode = ($cont->pop())($value);
                                $this->execute($nextNode, $ctx, $cont, $deferred);
                            }
                        },
                        fn($reason) => $deferred->reject($reason),
                    );
                } else {
                    if ($cont->isEmpty()) {
                        $deferred->resolve($result);
                    } else {
                        $nextNode = ($cont->pop())($result);
                        $this->execute($nextNode, $ctx, $cont, $deferred);
                    }
                }
                return;
            }
        }
        $deferred->reject(new RuntimeException('No handler for ' . get_class($node)));
    }

    public function runAll(array $programs, ?Context $ctx = null): PromiseInterface {
        $ctx ??= new Context();
        $deferred = new Deferred();
        $results = [];
        $count = count($programs);
        $completed = 0;

        if ($count === 0) {
            $deferred->resolve([]);
            return $deferred->promise();
        }

        foreach ($programs as $i => $program) {
            $this->run($program, $ctx)->then(
                function ($result) use (&$results, $i, &$completed, $count, $deferred) {
                    $results[$i] = $result;
                    $completed++;
                    if ($completed === $count) {
                        ksort($results);
                        $deferred->resolve(array_values($results));
                    }
                },
                fn($reason) => $deferred->reject($reason),
            );
        }
        return $deferred->promise();
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