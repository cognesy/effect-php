<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\Clock\SystemClock;
use EffectPHP\Core\Runtime\Fiber\FiberScheduler;
use EffectPHP\Core\Runtime\Fiber\FiberSleepEffectHandler;
use EffectPHP\Core\Runtime\Fiber\FiberTimeoutEffectHandler;
use EffectPHP\Core\Runtime\EffectHandlerRegistry;
use EffectPHP\Core\Effects\ProvideContextEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Either;
use EffectPHP\Core\Cause\Cause;
use Fiber;

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
    private EffectHandlerRegistry $handlerRegistry;
    private Context $rootContext;

    public function __construct(?Context $rootContext = null)
    {
        $this->scheduler = new FiberScheduler();
        $this->rootContext = $rootContext ?? $this->createDefaultContext();
        $this->handlerRegistry = $this->createFiberHandlerRegistry();
    }

    private function createDefaultContext(): Context
    {
        $context = Context::empty();
        $context = $context->withService(Clock::class, new SystemClock());
        return $context;
    }

    private function createFiberHandlerRegistry(): EffectHandlerRegistry
    {
        $registry = new EffectHandlerRegistry();
        
        // Register fiber-aware versions of sleep and timeout handlers
        // These will be checked first due to registration order
        $registry->register(new FiberSleepEffectHandler());
        $registry->register(new FiberTimeoutEffectHandler());
        
        return $registry;
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
            $handler = $this->handlerRegistry->getHandler($current);
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
                } while ($continuation instanceof \EffectPHP\Core\Effects\CatchEffect);
                
                $current = $continuation($result->value);
                continue;
            }
            
            // Handle failure cases - check stack for error handlers
            if ($result instanceof FailureEffect) {
                // Search stack for error handlers (CatchEffect)
                while (!empty($stack)) {
                    $stackItem = array_pop($stack);
                    
                    if ($stackItem instanceof \EffectPHP\Core\Effects\CatchEffect) {
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

    public function tryRun(Effect $effect, ?Context $context = null): Effect
    {
        try {
            $result = $this->run($effect, $context);
            return new SuccessEffect($result);
        } catch (\Throwable $e) {
            return new FailureEffect(Cause::fail($e));
        }
    }

    public function runSafely(Effect $effect): Either
    {
        try {
            $result = $this->run($effect);
            return Either::right($result);
        } catch (\Throwable $e) {
            return Either::left($e);
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
}