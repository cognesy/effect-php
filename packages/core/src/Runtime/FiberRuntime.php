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
use EffectPHP\Core\Result\Result;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Promise\Adapters\SyncPromiseAdapter;
use EffectPHP\Core\Fiber\FiberScheduler;
use EffectPHP\Core\Fiber\FiberSleepEffectHandler;
use EffectPHP\Core\Fiber\FiberTimeoutEffectHandler;
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
use Fiber;
use LogicException;

/**
 * Fiber-based runtime for proper async execution and virtual time support
 * 
 * This runtime uses PHP fibers to enable:
 * - True suspension/resumption of effects
 * - Proper virtual time testing with TestClock
 * - Racing between operations (timeout vs sleep)
 * - Non-blocking async execution
 */
final class FiberRuntime implements Runtime
{
    private FiberScheduler $scheduler;
    /** @var EffectHandler[] */
    private array $handlers;
    private Context $rootContext;

    public function __construct(?Context $rootContext = null)
    {
        $this->scheduler = new FiberScheduler();
        $this->rootContext = $rootContext ?? $this->createDefaultContext();
        $this->handlers = $this->createHandlers();
    }

    private function createDefaultContext(): Context
    {
        $context = Context::empty();
        $context = $context->withService(Clock::class, new SystemClock());
        return $context;
    }

    /**
     * Create handlers specific to FiberRuntime (fiber-based execution)
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
            // Fiber-aware versions override default ones
            new FiberSleepEffectHandler(),
            new FiberTimeoutEffectHandler(),
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

    public function getScheduler(): FiberScheduler
    {
        return $this->scheduler;
    }

    public function run(Effect $effect, ?Context $context = null): mixed
    {
        $context = $context ? $this->rootContext->mergeWith($context) : $this->rootContext;
        
        // Create and start a fiber for the effect
        $fiber = new Fiber(function() use ($effect, $context) {
            return $this->unsafeRunInternal($effect, $context);
        });
        
        $fiber->start();
        
        // Run the scheduler until completion
        while (!$fiber->isTerminated()) {
            $this->scheduler->tick();
            
            if ($fiber->isSuspended()) {
                // Check if any scheduled operations should resume this fiber
                if ($this->scheduler->shouldResumeFiber($fiber)) {
                    $resumeValue = $this->scheduler->getResumeValue($fiber);
                    $fiber->resume($resumeValue);
                }
            }
        }
        
        return $fiber->getReturn();
    }

    /**
     * Execute effect, throwing on failure (required by Runtime interface)
     */
    public function unsafeRun(Effect $effect): mixed
    {
        return $this->run($effect, null);
    }

    /**
     * Execute effect without fiber wrapper (for internal use)
     */
    private function unsafeRunInternal(Effect $effect, Context $context): mixed
    {
        $current = $effect;
        $stack = [];

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
            
            // Continue with returned effect
            $current = $result;
        }
    }

    /**
     * @psalm-return FailureEffect<\Throwable>|SuccessEffect<mixed>
     */
    public function tryRun(Effect $effect, ?Context $context = null): FailureEffect|SuccessEffect
    {
        try {
            $result = $this->run($effect, $context);
            return new SuccessEffect($result);
        } catch (\Throwable $e) {
            return new FailureEffect(Cause::fail($e));
        }
    }

    public function runSafely(Effect $effect): Result
    {
        try {
            $result = $this->run($effect);
            return Result::succeed($result);
        } catch (\Throwable $e) {
            return Result::die($e);
        }
    }

    public function withContext(Context $context): static
    {
        return new self($context);
    }

    public function getName(): string
    {
        return 'FiberRuntime';
    }

    // ===== EffectTS-style Execution APIs =====

    public function runSync(Effect $effect): mixed
    {
        return $this->run($effect);
    }

    public function runPromise(Effect $effect): Promise
    {
        // Create a promise that wraps fiber execution
        try {
            $result = $this->run($effect);
            return $this->createResolvedPromise($result);
        } catch (\Throwable $error) {
            return $this->createRejectedPromise($error);
        }
    }

    public function runCallback(Effect $effect, callable $callback): void
    {
        // Execute asynchronously with callback
        $fiber = new \Fiber(function() use ($effect, $callback) {
            try {
                $result = $this->unsafeRunInternal($effect, $this->rootContext);
                $callback(null, $result); // Node.js style: (error, result)
            } catch (\Throwable $error) {
                $callback($error, null);
            }
        });

        $fiber->start();
        
        // Run scheduler until fiber completes
        while (!$fiber->isTerminated()) {
            $this->scheduler->tick();
            
            if ($fiber->isSuspended()) {
                // Check if any scheduled operations should resume this fiber
                if ($this->scheduler->shouldResumeFiber($fiber)) {
                    $resumeValue = $this->scheduler->getResumeValue($fiber);
                    $fiber->resume($resumeValue);
                }
            }
        }
    }

    public function runFork(Effect $effect): FiberHandle
    {
        // Create a new fiber and return handle
        $fiber = new \Fiber(function() use ($effect) {
            return $this->unsafeRunInternal($effect, $this->rootContext);
        });

        $fiber->start();
        return new \EffectPHP\Core\Fiber\PHPFiberHandle($fiber);
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
            $result = $this->run($effect);
            return Result::succeed($result);
        } catch (\Throwable $error) {
            return Result::die($error);
        }
    }

    public function runPromiseResult(Effect $effect): Promise
    {
        // Create promise that will resolve asynchronously when fiber completes
        $adapter = new SyncPromiseAdapter();
        
        return $adapter->fromCallable(function() use ($effect) {
            try {
                $result = $this->run($effect); // This will run the fiber scheduler
                return Result::succeed($result);
            } catch (\Throwable $error) {
                return Result::die($error);
            }
        });
    }
}