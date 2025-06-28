
## Phase 4: Error Handling & Service Layer (Months 7-8)
### Production-Ready Error Management & Dependency Injection

### Module 4: Robust Error Handling

#### 4.1 Error Accumulation & Recovery
```php
<?php declare(strict_types=1);

namespace EffectPHP\Error;

/**
 * Accumulates multiple errors for batch validation.
 * 
 * @template E
 */
final class ErrorAccumulator
{
    /** @var array<E> */
    private array $errors = [];
    
    /**
     * @param E $error
     */
    public function add(mixed $error): void
    {
        $this->errors[] = $error;
    }
    
    /**
     * @return array<E>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    public function count(): int
    {
        return \count($this->errors);
    }
    
    /**
     * Create an Effect that fails with accumulated errors if any exist.
     * 
     * @template A
     * @param A $successValue
     * @return Effect<never, array<E>, A>
     */
    public function toEffect(mixed $successValue): Effect
    {
        return $this->hasErrors()
            ? Effect::fail($this->errors)
            : Effect::succeed($successValue);
    }
}

/**
 * Validates multiple values and accumulates errors.
 */
final class Validator
{
    /**
     * Validate multiple values in parallel, accumulating all errors.
     * 
     * @template A, E
     * @param array<Effect<never, E, A>> $effects
     * @return Effect<never, array<E>, array<A>>
     */
    public static function validateAll(array $effects): Effect
    {
        return Effect::async(function() use ($effects) {
            $accumulator = new ErrorAccumulator();
            $results = [];
            
            foreach ($effects as $index => $effect) {
                try {
                    $runtime = new Runtime();
                    $exit = $runtime->runExit($effect);
                    
                    if ($exit->isSuccess()) {
                        $results[$index] = $exit->getValue();
                    } else {
                        $accumulator->add($exit->getCause()->error);
                    }
                } catch (\Throwable $e) {
                    $accumulator->add($e);
                }
            }
            
            return $accumulator->hasErrors()
                ? Exit::fail($accumulator->getErrors())
                : Exit::succeed($results);
        });
    }
    
    /**
     * Validate with early termination on first error.
     * 
     * @template A, E
     * @param array<Effect<never, E, A>> $effects
     * @return Effect<never, E, array<A>>
     */
    public static function validateSequential(array $effects): Effect
    {
        return \array_reduce(
            $effects,
            function(Effect $acc, Effect $effect) {
                return $acc->flatMap(function(array $results) use ($effect) {
                    return $effect->map(function($value) use ($results) {
                        $results[] = $value;
                        return $results;
                    });
                });
            },
            Effect::succeed([])
        );
    }
}
```

#### 4.2 Retry Policies & Circuit Breaker
```php
<?php declare(strict_types=1);

/**
 * Retry policy for Effect execution.
 */
abstract readonly class RetryPolicy
{
    abstract public function shouldRetry(int $attempt, \Throwable $error): bool;
    abstract public function delay(int $attempt): float;
    
    /**
     * Fixed delay between retries.
     */
    public static function fixed(int $maxAttempts, float $delay): self
    {
        return new class($maxAttempts, $delay) extends RetryPolicy {
            public function __construct(
                private int $maxAttempts,
                private float $delay
            ) {}
            
            public function shouldRetry(int $attempt, \Throwable $error): bool
            {
                return $attempt < $this->maxAttempts;
            }
            
            public function delay(int $attempt): float
            {
                return $this->delay;
            }
        };
    }
    
    /**
     * Exponential backoff with jitter.
     */
    public static function exponential(
        int $maxAttempts,
        float $baseDelay = 1.0,
        float $maxDelay = 60.0,
        float $jitter = 0.1
    ): self {
        return new class($maxAttempts, $baseDelay, $maxDelay, $jitter) extends RetryPolicy {
            public function __construct(
                private int $maxAttempts,
                private float $baseDelay,
                private float $maxDelay,
                private float $jitter
            ) {}
            
            public function shouldRetry(int $attempt, \Throwable $error): bool
            {
                return $attempt < $this->maxAttempts;
            }
            
            public function delay(int $attempt): float
            {
                $delay = $this->baseDelay * (2 ** $attempt);
                $delay = \min($delay, $this->maxDelay);
                
                // Add jitter
                $jitterAmount = $delay * $this->jitter;
                $delay += \random_int(-$jitterAmount * 100, $jitterAmount * 100) / 100;
                
                return \max(0, $delay);
            }
        };
    }
    
    /**
     * Only retry on specific error types.
     */
    public function retryIf(callable $predicate): self
    {
        $parent = $this;
        return new class($parent, $predicate) extends RetryPolicy {
            public function __construct(
                private RetryPolicy $parent,
                private \Closure $predicate
            ) {}
            
            public function shouldRetry(int $attempt, \Throwable $error): bool
            {
                return $this->parent->shouldRetry($attempt, $error) 
                    && ($this->predicate)($error);
            }
            
            public function delay(int $attempt): float
            {
                return $this->parent->delay($attempt);
            }
        };
    }
}

/**
 * Effect extension for retry functionality.
 */
final readonly class RetryEffect extends Effect
{
    public function __construct(
        private Effect $source,
        private RetryPolicy $policy
    ) {}
    
    public function map(callable $f): Effect
    {
        return new RetryEffect($this->source->map($f), $this->policy);
    }
    
    public function flatMap(callable $f): Effect
    {
        return new RetryEffect($this->source->flatMap($f), $this->policy);
    }
    
    public function provide(mixed $environment): Effect
    {
        return new RetryEffect($this->source->provide($environment), $this->policy);
    }
    
    public function catchAll(callable $f): Effect
    {
        return new RetryEffect($this->source->catchAll($f), $this->policy);
    }
}

/**
 * Circuit breaker for failing fast when service is down.
 */
final class CircuitBreaker
{
    private int $failures = 0;
    private ?float $openedAt = null;
    private CircuitState $state = CircuitState::CLOSED;
    
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly float $timeout = 60.0,
        private readonly float $successThreshold = 0.5
    ) {}
    
    /**
     * Execute an Effect through the circuit breaker.
     * 
     * @template R, E, A
     * @param Effect<R, E, A> $effect
     * @return Effect<R, E|CircuitOpenException, A>
     */
    public function execute(Effect $effect): Effect
    {
        return Effect::sync(function() use ($effect) {
            if ($this->state === CircuitState::OPEN) {
                if ($this->shouldAttemptReset()) {
                    $this->state = CircuitState::HALF_OPEN;
                } else {
                    throw new CircuitOpenException('Circuit breaker is open');
                }
            }
            
            try {
                $runtime = new Runtime();
                $result = $runtime->run($effect);
                $this->onSuccess();
                return $result;
            } catch (\Throwable $e) {
                $this->onFailure();
                throw $e;
            }
        });
    }
    
    private function shouldAttemptReset(): bool
    {
        return $this->openedAt !== null 
            && (\microtime(true) - $this->openedAt) >= $this->timeout;
    }
    
    private function onSuccess(): void
    {
        $this->failures = 0;
        $this->state = CircuitState::CLOSED;
        $this->openedAt = null;
    }
    
    private function onFailure(): void
    {
        $this->failures++;
        
        if ($this->failures >= $this->failureThreshold) {
            $this->state = CircuitState::OPEN;
            $this->openedAt = \microtime(true);
        }
    }
}

enum CircuitState
{
    case CLOSED;
    case OPEN;
    case HALF_OPEN;
}
```
