<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\ProvideContextEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Either;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\Clock\SystemClock;
use EffectPHP\Core\Runtime\Internal\RuntimeConfig;
use EffectPHP\Core\Runtime\EffectHandlerRegistry;
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
    private EffectHandlerRegistry $handlerRegistry;

    public function __construct(Context $rootContext = null)
    {
        $this->rootContext = $rootContext ?? $this->createDefaultContext();
        $this->handlerRegistry = new EffectHandlerRegistry();
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
            
            $current = $result;
        }
    }

    public function tryRun(Effect $effect, Context $context): Effect
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