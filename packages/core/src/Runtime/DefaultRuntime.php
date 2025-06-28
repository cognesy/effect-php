<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Clock\SystemClock;
use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\FiberHandle;
use EffectPHP\Core\Contracts\Promise;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\CatchEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\ProvideContextEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Either;
use EffectPHP\Core\Result;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Promise\Adapters\SyncPromiseAdapter;
use EffectPHP\Core\Runtime\Handlers\AsyncPromiseEffectHandler;
use EffectPHP\Core\Runtime\Handlers\CatchEffectHandler;
use EffectPHP\Core\Runtime\Handlers\EnsuringEffectHandler;
use EffectPHP\Core\Runtime\Handlers\FailureEffectHandler;
use EffectPHP\Core\Runtime\Handlers\FlatMapEffectHandler;
use EffectPHP\Core\Runtime\Handlers\MapEffectHandler;
use EffectPHP\Core\Runtime\Handlers\NeverEffectHandler;
use EffectPHP\Core\Runtime\Handlers\OrElseEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ParallelEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ProvideContextEffectHandler;
use EffectPHP\Core\Runtime\Handlers\RaceEffectHandler;
use EffectPHP\Core\Runtime\Handlers\RetryEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ScopeEffectHandler;
use EffectPHP\Core\Runtime\Handlers\ServiceAccessEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SleepEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SuccessEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SuspendEffectHandler;
use EffectPHP\Core\Runtime\Handlers\SyncEffectHandler;
use EffectPHP\Core\Runtime\Handlers\TimeoutEffectHandler;
use LogicException;
use Throwable;

/**
 * Default synchronous runtime implementation
 * 
 * This is a baseline implementation that executes effects synchronously
 * using a stack-safe execution model. For async environments like Swoole,
 * ReactPHP, or AmpPHP, implement custom Runtime classes.
 */
final class DefaultRuntime implements Runtime
{
    private static ?self $instance = null;
    private Context $rootContext;
    /** @var EffectHandler[] */
    private array $handlers;

    public function __construct(Context $rootContext = null)
    {
        $this->rootContext = $rootContext ?? $this->createDefaultContext();
        $this->handlers = $this->createHandlers();
    }

    public static function current(): self
    {
        return self::$instance ??= new self();
    }

    public static function createWith(Context $rootContext): self
    {
        return new self($rootContext);
    }

    public function withContext(Context $context): static
    {
        return new self($context);
    }

    public function getName(): string
    {
        return 'DefaultRuntime';
    }

    // ===== EffectTS-style Execution APIs =====

    public function runSync(Effect $effect): mixed
    {
        return $this->unsafeRun($effect);
    }

    public function runPromise(Effect $effect): Promise
    {
        // Create a synchronous promise that immediately resolves/rejects
        try {
            $result = $this->unsafeRun($effect);
            return $this->createResolvedPromise($result);
        } catch (\Throwable $error) {
            return $this->createRejectedPromise($error);
        }
    }

    public function runCallback(Effect $effect, callable $callback): void
    {
        try {
            $result = $this->unsafeRun($effect);
            $callback(null, $result); // Node.js style: (error, result)
        } catch (\Throwable $error) {
            $callback($error, null);
        }
    }

    public function runFork(Effect $effect): FiberHandle
    {
        // In DefaultRuntime, we execute immediately and return completed handle
        try {
            $result = $this->unsafeRun($effect);
            return new DefaultFiberHandle($result);
        } catch (\Throwable $error) {
            return new DefaultFiberHandle(null, $error);
        }
    }

    /**
     * Create a resolved promise using SyncPromiseAdapter
     */
    private function createResolvedPromise(mixed $value): Promise
    {
        $adapter = new SyncPromiseAdapter();
        return $adapter->resolve($value);
    }

    /**
     * Create a rejected promise using SyncPromiseAdapter
     */
    private function createRejectedPromise(\Throwable $error): Promise
    {
        $adapter = new SyncPromiseAdapter();
        return $adapter->reject($error);
    }

    public function runSyncResult(Effect $effect): Result
    {
        try {
            $result = $this->unsafeRun($effect);
            return Result::succeed($result);
        } catch (\Throwable $error) {
            return Result::die($error);
        }
    }

    public function runPromiseResult(Effect $effect): Promise
    {
        // Create promise that resolves to Result (never rejects)
        $exit = $this->runSyncResult($effect);
        $adapter = new SyncPromiseAdapter();
        return $adapter->resolve($exit);
    }

    /**
     * Execute effect safely returning Either
     *
     * @template A
     * @template E of Throwable
     * @param \EffectPHP\Core\Contracts\Effect<never, E, A> $effect
     * @return Either<E, A>
     */
    public function runSafely(Effect $effect): Either
    {
        try {
            $result = $this->unsafeRun($effect);
            return Either::right($result);
        } catch (Throwable $e) {
            return Either::left($e);
        }
    }

    /**
     * Execute effect, throwing on failure
     * Stack-safe execution with continuation optimization
     *
     * @template A
     * @template E of Throwable
     * @param \EffectPHP\Core\Contracts\Effect<never, E, A> $effect
     * @return A
     * @throws Throwable
     */
    public function unsafeRun(Effect $effect): mixed
    {
        $current = $effect;
        $stack = [];
        $context = $this->rootContext;

        while (true) {
            // Handle context merging for ProvideContextEffect
            if ($current instanceof ProvideContextEffect) {
                $context = $context->mergeWith($current->context);
            }

            // Get appropriate handler and delegate
            $handler = $this->getHandler($current);
            $result = $handler->handle($current, $stack, $context, $this);
            
            // Handle completion cases
            if ($result instanceof SuccessEffect) {
                if (empty($stack)) {
                    return $result->value;
                }
                
                // Pop and apply continuation from stack, skipping CatchEffect objects
                do {
                    if (empty($stack)) {
                        return $result->value;
                    }
                    $continuation = array_pop($stack);
                } while ($continuation instanceof CatchEffect);
                
                $current = $continuation($result->value);
                continue;
            }
            
            // Handle failure cases - check stack for error handlers
            if ($result instanceof FailureEffect) {
                // Search stack for error handlers (CatchEffect)
                while (!empty($stack)) {
                    $stackItem = array_pop($stack);
                    
                    if ($stackItem instanceof CatchEffect) {
                        // Check if this catch handler can handle the error
                        $error = $result->cause->error;
                        
                        if (is_string($stackItem->errorType)) {
                            // String type check
                            if ($error instanceof $stackItem->errorType) {
                                $current = ($stackItem->handler)($error);
                                continue 2; // Continue outer while loop
                            }
                        } else {
                            // Callable type check
                            if (($stackItem->errorType)($error)) {
                                $current = ($stackItem->handler)($error);
                                continue 2; // Continue outer while loop
                            }
                        }
                    }
                    // If not a matching catch handler, continue searching
                }
                
                // No error handler found, throw the error
                throw $result->cause->error;
            }
            
            $current = $result;
        }
    }

    /**
     * @psalm-return FailureEffect<Throwable>|SuccessEffect<mixed>
     */
    public function tryRun(Effect $effect, Context $context): FailureEffect|SuccessEffect
    {
        try {
            $runtime = $this->withContext($context);
            $result = $runtime->unsafeRun($effect);
            return new SuccessEffect($result);
        } catch (Throwable $e) {
            return new FailureEffect(Cause::fail($e));
        }
    }

    /**
     * Create handlers specific to DefaultRuntime (synchronous execution)
     * 
     * @return EffectHandler[]
     */
    private function createHandlers(): array
    {
        return [
            new AsyncPromiseEffectHandler(new SyncPromiseAdapter()),
            new CatchEffectHandler(),
            new EnsuringEffectHandler(),
            new FailureEffectHandler(),
            new FlatMapEffectHandler(),
            new MapEffectHandler(),
            new NeverEffectHandler(),
            new OrElseEffectHandler(),
            new ParallelEffectHandler(),
            new ProvideContextEffectHandler(),
            new RaceEffectHandler(),
            new RetryEffectHandler(),
            new ScopeEffectHandler(),
            new ServiceAccessEffectHandler(),
            new SleepEffectHandler(),
            new SuccessEffectHandler(),
            new SuspendEffectHandler(),
            new SyncEffectHandler(),
            new TimeoutEffectHandler(),
        ];
    }

    /**
     * Get handler for specific effect type
     */
    private function getHandler(Effect $effect): EffectHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($effect)) {
                return $handler;
            }
        }

        throw new LogicException('No handler found for effect type: ' . get_class($effect));
    }

    /**
     * Create default context with built-in services
     * 
     * @return Context
     */
    private function createDefaultContext(): Context
    {
        return Context::empty()
            ->withService(Clock::class, new SystemClock());
    }

}