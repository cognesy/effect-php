<?php declare(strict_types=1);

namespace EffectPHP\Core\Runtimes;

use EffectPHP\Core\Context;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Handlers\BindHandler;
use EffectPHP\Core\Handlers\FailHandler;
use EffectPHP\Core\Handlers\NoAsyncHandler;
use EffectPHP\Core\Handlers\ProvideHandler;
use EffectPHP\Core\Handlers\PureHandler;
use EffectPHP\Core\Handlers\ReserveHandler;
use EffectPHP\Core\Handlers\ServiceHandler;
use EffectPHP\Core\Handlers\SleepHandler;
use EffectPHP\Core\Handlers\SuspendHandler;
use EffectPHP\Core\RuntimeState;
use EffectPHP\Core\Scope;
use EffectPHP\Utils\ContinuationStack;
use EffectPHP\Utils\Result\Result;
use RuntimeException;
use Throwable;

final class SyncRuntime implements Runtime
{
    /** @var EffectHandler[] */
    private readonly array $handlers;
    private readonly ?Context $context;

    public function __construct(
        array $handlers = [],
        ?Context $context = null,
    ) {
        // caller may override the default set
        $this->handlers = $handlers ?: $this->defaultHandlers();
        // context is optional; if not provided, use empty context
        $this->context = $context ?? Context::empty();
    }

    public function withHandlers(EffectHandler ...$handlers): self {
        return new self($handlers, $this->context);
    }

    public function withContext(Context $context): self {
        return new self($this->handlers, $context);
    }

    /** Run an effect program to completion. */
    public function run(Effect $program): mixed {
        $context = $this->context ?? Context::empty();
        $stack = new ContinuationStack();
        $next = null;

        // Use scope from context if available, otherwise create new one
        $scope = $context->has(Scope::class) ? $context->get(Scope::class) : new Scope();

        $state = new RuntimeState(
            context: $context,
            stack: $stack,
            scope: $scope,
            value: $next,
        );
        $node = $program;

        try {
            while (true) {
                $handler = $this->findHandler($node);

                // execute handler and unpack state
                $newState = $handler->handle($node, $state);
                $context = $newState->context;
                $stack = $newState->stack;
                $scope = $newState->scope;
                $next = $newState->value;
                $state = $state->with(context: $context, stack: $stack, scope: $scope, value: $next);

                // handler returned another EffectNode → keep looping
                if ($next instanceof Effect) {
                    $node = $next;
                    continue;
                }

                // otherwise we have a value; resume or finish
                if ($stack->isEmpty()) {
                    // program complete - close scope and return result
                    $scope->close();
                    return $next;
                }
                $node = ($stack->pop())($next);
            }
        } catch (Throwable $e) {
            // close scope before re-throwing
            $scope->close();
            throw $e;
        }
    }

    public function tryRun(Effect $program): Result {
        return Result::try(function () use ($program) {
            return $this->run($program);
        });
    }

    public function runAll(Effect ...$programs): array {
        $results = [];
        foreach ($programs as $program) {
            $results[] = $this->run($program);
        }
        return $results;
    }

    public function tryRunAll(Effect ...$programs): Result {
        return Result::try(function () use ($programs) {
            return $this->runAll(...$programs);
        });
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function findHandler(Effect $node): EffectHandler {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($node)) {
                return $handler;
            }
        }
        throw new RuntimeException('No handler for: ' . get_class($node));
    }

    private function defaultHandlers(): array {
        return [
            new BindHandler(),
            new FailHandler(),
            new NoAsyncHandler(),
            new ProvideHandler(),
            new PureHandler(),
            new ReserveHandler(),
            new ServiceHandler(),
            new SleepHandler(),
            new SuspendHandler(),
        ];
    }
}
