## Phase 2: Runtime & Execution (Months 4-5)
### Structured Concurrency Engine

**Goal**: Reliable execution engine with resource safety and fiber-based concurrency.

### Module 2: Runtime System

#### 2.1 Core Runtime Implementation
```php
<?php declare(strict_types=1);

namespace EffectPHP\Runtime;

use EffectPHP\Core\Effect;
use EffectPHP\Core\Exit;

/**
 * The main runtime for executing Effects with proper resource management.
 */
final class Runtime
{
    private readonly bool $fiberSupported;
    private readonly \WeakMap $scopes;
    private readonly ExecutionContext $rootContext;
    
    public function __construct(
        private readonly RuntimeConfig $config = new RuntimeConfig()
    ) {
        $this->fiberSupported = \class_exists('Fiber');
        $this->scopes = new \WeakMap();
        $this->rootContext = new ExecutionContext();
    }
    
    /**
     * Execute an Effect and return its result.
     * 
     * @template R, E, A
     * @param Effect<R, E, A> $effect
     * @return A
     * @throws RuntimeException when effect fails
     */
    public function run(Effect $effect): mixed
    {
        $exit = $this->runExit($effect);
        
        return match($exit->isSuccess()) {
            true => $exit->getValue(),
            false => throw new RuntimeException(
                'Effect failed: ' . $exit->getCause()->prettyPrint()
            )
        };
    }
    
    /**
     * Execute an Effect and return the Exit result.
     * 
     * @template R, E, A
     * @param Effect<R, E, A> $effect
     * @return Exit<E, A>
     */
    public function runExit(Effect $effect): Exit
    {
        return $this->fiberSupported
            ? $this->runAsync($effect)
            : $this->runSync($effect);
    }
    
    /**
     * Synchronous execution path.
     */
    private function runSync(Effect $effect): Exit
    {
        try {
            $interpreter = new SyncInterpreter($this->rootContext);
            return $interpreter->run($effect);
        } catch (\Throwable $e) {
            return Exit::fail($e);
        }
    }
    
    /**
     * Asynchronous execution path using Fibers.
     */
    private function runAsync(Effect $effect): Exit
    {
        $scope = new Scope($this->rootContext);
        
        try {
            $fiber = new \Fiber(function() use ($effect, $scope) {
                $interpreter = new AsyncInterpreter($scope);
                return $interpreter->run($effect);
            });
            
            $result = $fiber->start();
            
            // Handle fiber suspension/resumption
            while (!$fiber->isTerminated()) {
                if ($fiber->isSuspended()) {
                    $suspended = $fiber->getReturn();
                    $result = $fiber->resume($this->handleSuspension($suspended));
                }
            }
            
            return $result;
        } catch (\Throwable $e) {
            return Exit::fail($e);
        } finally {
            $scope->close();
        }
    }
    
    private function handleSuspension(mixed $suspension): mixed
    {
        // Handle different types of suspensions (IO, timers, etc.)
        return match(true) {
            $suspension instanceof YieldedEffect => $this->runSync($suspension->effect),
            $suspension instanceof SleepRequest => $this->handleSleep($suspension),
            default => throw new \RuntimeException('Unknown suspension: ' . get_class($suspension))
        };
    }
    
    private function handleSleep(SleepRequest $request): void
    {
        \usleep((int)($request->duration * 1_000_000));
    }
}

/**
 * Configuration for the runtime.
 */
final readonly class RuntimeConfig
{
    public function __construct(
        public int $maxConcurrency = 1000,
        public float $defaultTimeout = 30.0,
        public bool $enableTracing = false,
        public bool $enableMetrics = false
    ) {}
}

/**
 * Execution context tracking resources and state.
 */
final class ExecutionContext
{
    private array $finalizers = [];
    private bool $closed = false;
    
    /**
     * Add a finalizer to be run when context closes.
     */
    public function addFinalizer(callable $cleanup): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Cannot add finalizer to closed context');
        }
        
        $this->finalizers[] = $cleanup;
    }
    
    /**
     * Run all finalizers and close the context.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        
        $this->closed = true;
        
        // Run finalizers in reverse order (LIFO)
        foreach (\array_reverse($this->finalizers) as $finalizer) {
            try {
                $finalizer();
            } catch (\Throwable $e) {
                // Log but don't propagate finalizer errors
                \error_log('Finalizer error: ' . $e->getMessage());
            }
        }
        
        $this->finalizers = [];
    }
}
```

#### 2.2 Effect Interpreters
```php
<?php declare(strict_types=1);

/**
 * Synchronous interpreter for Effects.
 */
final class SyncInterpreter
{
    public function __construct(
        private readonly ExecutionContext $context
    ) {}
    
    /**
     * @template R, E, A
     * @param Effect<R, E, A> $effect
     * @return Exit<E, A>
     */
    public function run(Effect $effect): Exit
    {
        return $this->interpret($effect);
    }
    
    private function interpret(Effect $effect): Exit
    {
        return match($effect::class) {
            SucceedEffect::class => Exit::succeed($effect->value),
            FailEffect::class => Exit::fail($effect->error),
            SyncEffect::class => $this->runSync($effect),
            MapEffect::class => $this->runMap($effect),
            FlatMapEffect::class => $this->runFlatMap($effect),
            CatchAllEffect::class => $this->runCatchAll($effect),
            ProvideEffect::class => $this->runProvide($effect),
            default => throw new \RuntimeException('Unknown effect type: ' . $effect::class)
        };
    }
    
    private function runSync(SyncEffect $effect): Exit
    {
        try {
            $result = ($effect->computation)();
            return Exit::succeed($result);
        } catch (\Throwable $e) {
            return Exit::fail($e);
        }
    }
    
    private function runMap(MapEffect $effect): Exit
    {
        $sourceExit = $this->interpret($effect->source);
        
        if (!$sourceExit->isSuccess()) {
            return $sourceExit;
        }
        
        try {
            $mapped = ($effect->mapper)($sourceExit->getValue());
            return Exit::succeed($mapped);
        } catch (\Throwable $e) {
            return Exit::fail($e);
        }
    }
    
    private function runFlatMap(FlatMapEffect $effect): Exit
    {
        $sourceExit = $this->interpret($effect->source);
        
        if (!$sourceExit->isSuccess()) {
            return $sourceExit;
        }
        
        try {
            $nextEffect = ($effect->mapper)($sourceExit->getValue());
            return $this->interpret($nextEffect);
        } catch (\Throwable $e) {
            return Exit::fail($e);
        }
    }
    
    private function runCatchAll(CatchAllEffect $effect): Exit
    {
        $sourceExit = $this->interpret($effect->source);
        
        if ($sourceExit->isSuccess()) {
            return $sourceExit;
        }
        
        try {
            $recoveryEffect = ($effect->handler)($sourceExit->getCause()->error);
            return $this->interpret($recoveryEffect);
        } catch (\Throwable $e) {
            return Exit::fail($e);
        }
    }
    
    private function runProvide(ProvideEffect $effect): Exit
    {
        // Create new context with provided environment
        $environmentContext = new EnvironmentContext($effect->environment);
        $interpreter = new SyncInterpreter($this->context);
        
        return $interpreter->interpret($effect->source);
    }
}

/**
 * Asynchronous interpreter using Fibers.
 */
final class AsyncInterpreter
{
    public function __construct(
        private readonly Scope $scope
    ) {}
    
    public function run(Effect $effect): Exit
    {
        return $this->interpret($effect);
    }
    
    private function interpret(Effect $effect): Exit
    {
        return match($effect::class) {
            AsyncEffect::class => $this->runAsync($effect),
            ForkEffect::class => $this->runFork($effect),
            SleepEffect::class => $this->runSleep($effect),
            default => (new SyncInterpreter($this->scope->context))->run($effect)
        };
    }
    
    private function runAsync(AsyncEffect $effect): Exit
    {
        try {
            $result = ($effect->computation)();
            return Exit::succeed($result);
        } catch (\Throwable $e) {
            return Exit::fail($e);
        }
    }
    
    private function runFork(ForkEffect $effect): Exit
    {
        $fiberHandle = $this->scope->fork($effect->effect);
        return Exit::succeed($fiberHandle);
    }
    
    private function runSleep(SleepEffect $effect): Exit
    {
        \Fiber::suspend(new SleepRequest($effect->duration));
        return Exit::succeed(null);
    }
}
```

#### 2.3 Structured Concurrency & Resource Management
```php
<?php declare(strict_types=1);

/**
 * Scope for managing concurrent Effects and their resources.
 */
final class Scope
{
    private readonly \WeakMap $resources;
    private readonly array $finalizers;
    private readonly array $fibers;
    private bool $closed = false;
    
    public function __construct(
        public readonly ExecutionContext $context
    ) {
        $this->resources = new \WeakMap();
        $this->finalizers = [];
        $this->fibers = [];
    }
    
    /**
     * Fork an Effect to run concurrently.
     * 
     * @template R, E, A
     * @param Effect<R, E, A> $effect
     * @return FiberHandle<A>
     */
    public function fork(Effect $effect): FiberHandle
    {
        if ($this->closed) {
            throw new \RuntimeException('Cannot fork from closed scope');
        }
        
        $fiber = new \Fiber(function() use ($effect) {
            $interpreter = new AsyncInterpreter($this);
            return $interpreter->run($effect);
        });
        
        $handle = new FiberHandle($fiber, $this);
        $this->fibers[] = $handle;
        $this->resources[$handle] = true;
        
        // Start the fiber
        try {
            $fiber->start();
        } catch (\Throwable $e) {
            $this->removeFiber($handle);
            throw $e;
        }
        
        return $handle;
    }
    
    /**
     * Add a managed resource with cleanup.
     */
    public function addResource(object $resource, callable $cleanup): void
    {
        if ($this->closed) {
            $cleanup();
            return;
        }
        
        $this->resources[$resource] = $cleanup;
    }
    
    /**
     * Close the scope and clean up all resources.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        
        $this->closed = true;
        
        // Interrupt all running fibers
        foreach ($this->fibers as $handle) {
            $handle->interrupt();
        }
        
        // Wait for fibers to complete (with timeout)
        $deadline = \microtime(true) + 5.0; // 5 second timeout
        foreach ($this->fibers as $handle) {
            $remaining = $deadline - \microtime(true);
            if ($remaining > 0) {
                $handle->await($remaining);
            }
        }
        
        // Clean up all managed resources
        foreach ($this->resources as $resource => $cleanup) {
            try {
                if (\is_callable($cleanup)) {
                    $cleanup();
                }
            } catch (\Throwable $e) {
                \error_log('Resource cleanup error: ' . $e->getMessage());
            }
        }
        
        $this->context->close();
    }
    
    private function removeFiber(FiberHandle $handle): void
    {
        $index = \array_search($handle, $this->fibers, true);
        if ($index !== false) {
            unset($this->fibers[$index]);
        }
        unset($this->resources[$handle]);
    }
}

/**
 * Handle for managing a forked Fiber.
 * 
 * @template A
 */
final class FiberHandle
{
    private bool $interrupted = false;
    
    public function __construct(
        private readonly \Fiber $fiber,
        private readonly Scope $scope
    ) {}
    
    /**
     * Wait for the fiber to complete and return its result.
     * 
     * @param float $timeout Timeout in seconds
     * @return A
     * @throws TimeoutException
     * @throws RuntimeException
     */
    public function await(float $timeout = \PHP_FLOAT_MAX): mixed
    {
        $deadline = \microtime(true) + $timeout;
        
        while (!$this->fiber->isTerminated()) {
            if (\microtime(true) > $deadline) {
                $this->interrupt();
                throw new TimeoutException('Fiber did not complete within timeout');
            }
            
            \usleep(1000); // 1ms polling
        }
        
        $exit = $this->fiber->getReturn();
        
        if ($exit->isSuccess()) {
            return $exit->getValue();
        }
        
        throw new RuntimeException('Fiber failed: ' . $exit->getCause()->prettyPrint());
    }
    
    /**
     * Interrupt the fiber.
     */
    public function interrupt(): void
    {
        if ($this->interrupted || $this->fiber->isTerminated()) {
            return;
        }
        
        $this->interrupted = true;
        
        // PHP Fibers don't have built-in interruption
        // We rely on cooperative cancellation via shared state
        try {
            if ($this->fiber->isSuspended()) {
                $this->fiber->throw(new InterruptedException('Fiber interrupted'));
            }
        } catch (\Throwable $e) {
            // Fiber may have terminated during interruption
        }
    }
    
    public function isRunning(): bool
    {
        return $this->fiber->isStarted() && !$this->fiber->isTerminated();
    }
    
    public function isCompleted(): bool
    {
        return $this->fiber->isTerminated();
    }
}

/**
 * Deferred value for one-shot communication between fibers.
 * 
 * @template A
 */
final class Deferred
{
    private bool $completed = false;
    private mixed $value = null;
    private array $callbacks = [];
    
    /**
     * Complete the deferred with a value.
     * 
     * @param A $value
     * @return bool True if this was the first completion
     */
    public function complete(mixed $value): bool
    {
        if ($this->completed) {
            return false;
        }
        
        $this->completed = true;
        $this->value = $value;
        
        // Notify all waiting callbacks
        foreach ($this->callbacks as $callback) {
            try {
                $callback($value);
            } catch (\Throwable $e) {
                \error_log('Deferred callback error: ' . $e->getMessage());
            }
        }
        
        $this->callbacks = [];
        return true;
    }
    
    /**
     * Create an Effect that waits for this deferred to complete.
     * 
     * @return Effect<never, never, A>
     */
    public function await(): Effect
    {
        return Effect::async(function() {
            if ($this->completed) {
                return $this->value;
            }
            
            // Suspend fiber until value is available
            \Fiber::suspend(new DeferredWait($this));
            return $this->value;
        });
    }
    
    /**
     * Add a callback to be run when deferred completes.
     * 
     * @param callable(A): void $callback
     */
    public function onComplete(callable $callback): void
    {
        if ($this->completed) {
            $callback($this->value);
        } else {
            $this->callbacks[] = $callback;
        }
    }
    
    public function isCompleted(): bool
    {
        return $this->completed;
    }
}
```

**Dependencies**: Phase 1  
**Technical Challenges**:
- Fiber cancellation requires cooperative design
- Resource cleanup must be deterministic
- Performance impact of WeakMap usage

**Success Criteria**:
- Can execute concurrent Effects safely
- Resource leaks prevented in all scenarios  
- Performance within 30% of native async PHP
