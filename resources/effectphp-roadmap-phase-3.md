## Phase 3: Flow Control & Composition (Month 6)
### Expressive Effect Composition

**Goal**: Ergonomic DSL for building complex Effect workflows.

### Module 3: Flow Control & Combinators

#### 3.1 Core Combinators Implementation
```php
<?php declare(strict_types=1);

// Concrete Effect implementations for flow control

/**
 * @template A
 */
final readonly class SucceedEffect extends Effect
{
    public function __construct(public mixed $value) {}
    
    public function map(callable $f): Effect
    {
        return new MapEffect($this, $f);
    }
    
    public function flatMap(callable $f): Effect
    {
        return new FlatMapEffect($this, $f);
    }
    
    public function provide(mixed $environment): Effect
    {
        return $this; // No environment dependency
    }
    
    public function catchAll(callable $f): Effect
    {
        return $this; // Cannot fail
    }
}

/**
 * @template E
 */
final readonly class FailEffect extends Effect
{
    public function __construct(public mixed $error) {}
    
    public function map(callable $f): Effect
    {
        return $this; // Failed effect stays failed
    }
    
    public function flatMap(callable $f): Effect
    {
        return $this;
    }
    
    public function provide(mixed $environment): Effect
    {
        return $this;
    }
    
    public function catchAll(callable $f): Effect
    {
        return new CatchAllEffect($this, $f);
    }
}

/**
 * @template R, E, A, B
 */
final readonly class MapEffect extends Effect
{
    public function __construct(
        public Effect $source,
        public \Closure $mapper
    ) {}
    
    public function map(callable $f): Effect
    {
        // Optimize by composing functions
        return new MapEffect(
            $this->source,
            fn($a) => $f(($this->mapper)($a))
        );
    }
    
    public function flatMap(callable $f): Effect
    {
        return new FlatMapEffect($this, $f);
    }
    
    public function provide(mixed $environment): Effect
    {
        return new MapEffect(
            $this->source->provide($environment),
            $this->mapper
        );
    }
    
    public function catchAll(callable $f): Effect
    {
        return new MapEffect(
            $this->source->catchAll($f),
            $this->mapper
        );
    }
}

/**
 * @template R, E, A, R2, E2, B
 */
final readonly class FlatMapEffect extends Effect
{
    public function __construct(
        public Effect $source,
        public \Closure $mapper
    ) {}
    
    public function map(callable $f): Effect
    {
        return new FlatMapEffect(
            $this->source,
            fn($a) => ($this->mapper)($a)->map($f)
        );
    }
    
    public function flatMap(callable $f): Effect
    {
        return new FlatMapEffect(
            $this->source,
            fn($a) => ($this->mapper)($a)->flatMap($f)
        );
    }
    
    public function provide(mixed $environment): Effect
    {
        return new FlatMapEffect(
            $this->source->provide($environment),
            fn($a) => ($this->mapper)($a)->provide($environment)
        );
    }
    
    public function catchAll(callable $f): Effect
    {
        return new FlatMapEffect(
            $this->source->catchAll($f),
            $this->mapper
        );
    }
}
```

#### 3.2 Do Notation via Generators
```php
<?php declare(strict_types=1);

/**
 * Execute a generator-based Effect workflow.
 * 
 * @template R, E, A
 * @param \Generator<Effect<R, E, mixed>, mixed, mixed, A> $generator
 * @return Effect<R, E, A>
 */
function effectDo(\Generator $generator): Effect
{
    return Effect::async(function() use ($generator) {
        $current = $generator->current();
        
        while ($generator->valid()) {
            if (!$current instanceof Effect) {
                throw new \RuntimeException('Generator must yield Effects');
            }
            
            // Execute the current effect
            $runtime = new Runtime();
            $result = $runtime->run($current);
            
            // Send result back to generator
            $current = $generator->send($result);
        }
        
        return $generator->getReturn();
    });
}

// Usage example:
function userWorkflow(int $userId): Effect
{
    return effectDo((function() use ($userId) {
        // Yield effects and get their results
        $user = yield User::findById($userId);
        $profile = yield Profile::findByUserId($user->id);
        $permissions = yield Permission::findByUserId($user->id);
        
        // Return final result
        return new UserWithDetails($user, $profile, $permissions);
    })());
}
```

#### 3.3 Pipeline & Fluent DSL
```php
<?php declare(strict_types=1);

/**
 * Pipeline builder for functional composition.
 */
final class Pipeline
{
    /**
     * @template A, B
     * @param A $input
     * @param callable(A): B ...$steps
     * @return B
     */
    public static function of(mixed $input, callable ...$steps): mixed
    {
        return \array_reduce($steps, fn($acc, $fn) => $fn($acc), $input);
    }
}

/**
 * Fluent builder for Effect composition.
 * 
 * @template R, E, A
 */
final class EffectBuilder
{
    /**
     * @param Effect<R, E, A> $effect
     */
    public function __construct(private Effect $effect) {}
    
    /**
     * @template B
     * @param callable(A): B $f
     * @return EffectBuilder<R, E, B>
     */
    public function map(callable $f): self
    {
        return new self($this->effect->map($f));
    }
    
    /**
     * @template R2, E2, B
     * @param callable(A): Effect<R2, E2, B> $f
     * @return EffectBuilder<R&R2, E|E2, B>
     */
    public function flatMap(callable $f): self
    {
        return new self($this->effect->flatMap($f));
    }
    
    /**
     * @param callable(A): bool $predicate
     * @param mixed $error Error to emit if predicate fails
     * @return EffectBuilder<R, E|mixed, A>
     */
    public function filter(callable $predicate, mixed $error = 'Filter failed'): self
    {
        return $this->flatMap(function($value) use ($predicate, $error) {
            return $predicate($value) 
                ? Effect::succeed($value)
                : Effect::fail($error);
        });
    }
    
    /**
     * @template B
     * @param callable(A): B $f
     * @return EffectBuilder<R, E, A>
     */
    public function tap(callable $f): self
    {
        return $this->map(function($value) use ($f) {
            $f($value);
            return $value;
        });
    }
    
    /**
     * @param callable(): void $f
     * @return EffectBuilder<R, E, A>
     */
    public function ensuring(callable $f): self
    {
        return new self(new EnsuringEffect($this->effect, $f));
    }
    
    /**
     * @param float $seconds
     * @return EffectBuilder<R, E|TimeoutException, A>
     */
    public function timeout(float $seconds): self
    {
        return new self(new TimeoutEffect($this->effect, $seconds));
    }
    
    /**
     * @template R2
     * @param R2 $environment
     * @return EffectBuilder<never, E, A> when R2 satisfies R
     */
    public function provide(mixed $environment): self
    {
        return new self($this->effect->provide($environment));
    }
    
    /**
     * @return Effect<R, E, A>
     */
    public function build(): Effect
    {
        return $this->effect;
    }
}

// Extension method to enable fluent syntax
trait EffectSyntax
{
    /**
     * @return EffectBuilder<R, E, A>
     */
    public function pipe(): EffectBuilder
    {
        return new EffectBuilder($this);
    }
}

// Add to base Effect class
abstract readonly class Effect
{
    use EffectSyntax;
    
    // ... existing methods
}

// Usage examples:
$result = Effect::succeed(42)
    ->pipe()
    ->map(fn($x) => $x * 2)
    ->filter(fn($x) => $x > 50, 'Value too small')
    ->flatMap(fn($x) => Effect::succeed("Result: $x"))
    ->timeout(5.0)
    ->build();
```

#### 3.4 Pattern Matching Utilities
```php
<?php declare(strict_types=1);

/**
 * Pattern matching for Effects and ADTs.
 */
final class Match
{
    /**
     * Match on Exit values.
     * 
     * @template E, A, B
     * @param Exit<E, A> $exit
     * @param callable(A): B $onSuccess
     * @param callable(E): B $onFailure
     * @param callable(): B $onInterruption
     * @return B
     */
    public static function exit(
        Exit $exit,
        callable $onSuccess,
        callable $onFailure,
        callable $onInterruption
    ): mixed {
        return match(true) {
            $exit->isSuccess() => $onSuccess($exit->getValue()),
            $exit->isFailure() => $onFailure($exit->getCause()->error),
            $exit->isInterrupted() => $onInterruption(),
        };
    }
    
    /**
     * Match on Option values.
     * 
     * @template A, B
     * @param Option<A> $option
     * @param callable(A): B $onSome
     * @param callable(): B $onNone
     * @return B
     */
    public static function option(
        Option $option,
        callable $onSome,
        callable $onNone
    ): mixed {
        return match($option) {
            Option::Some => $onSome($option->getValue()),
            Option::None => $onNone(),
        };
    }
    
    /**
     * Match on Either values.
     * 
     * @template L, R, B
     * @param Either<L, R> $either
     * @param callable(L): B $onLeft
     * @param callable(R): B $onRight
     * @return B
     */
    public static function either(
        Either $either,
        callable $onLeft,
        callable $onRight
    ): mixed {
        return match($either) {
            Either::Left => $onLeft($either->getValue()),
            Either::Right => $onRight($either->getValue()),
        };
    }
}

// Usage examples:
$result = Match::exit(
    $exit,
    onSuccess: fn($value) => "Success: $value",
    onFailure: fn($error) => "Error: $error",
    onInterruption: fn() => "Interrupted"
);
```

**Dependencies**: Phase 2  
**Technical Challenges**:
- Generator-based do notation needs careful error handling
- Performance optimization for chained combinators
- Type inference complexity

**Success Criteria**:
- Ergonomic Effect composition workflows
- Do notation works with complex nested effects
- Performance acceptable for real applications
