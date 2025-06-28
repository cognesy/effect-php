## Phase 1: Core Foundation (Months 2-3)
### Type-Safe Algebraic Data Types

**Goal**: Establish mathematical foundations with realistic PHP8 type constraints.

### Module 1: Core Effect Types

#### 1.1 The Effect Type - Core Abstraction
```php
<?php declare(strict_types=1);

namespace EffectPHP\Core;

/**
 * The core Effect type representing a description of a synchronous or 
 * asynchronous computation that requires an environment R, may fail with 
 * an error E, or succeed with a value A.
 * 
 * @template R - Environment/Requirements 
 * @template E - Error type
 * @template A - Success value type
 * 
 * @psalm-immutable
 */
abstract readonly class Effect
{
    /**
     * Transform the success value of this effect.
     * 
     * @template B
     * @param callable(A): B $f
     * @return Effect<R, E, B>
     */
    abstract public function map(callable $f): Effect;
    
    /**
     * Sequentially compose this effect with another effect.
     * 
     * @template R2
     * @template E2  
     * @template B
     * @param callable(A): Effect<R2, E2, B> $f
     * @return Effect<R&R2, E|E2, B>
     */
    abstract public function flatMap(callable $f): Effect;
    
    /**
     * Provide the required environment to this effect.
     * 
     * @template R2
     * @param R2 $environment  
     * @return Effect<never, E, A> when R2 = R
     */
    abstract public function provide(mixed $environment): Effect;
    
    /**
     * Handle errors in this effect.
     * 
     * @template E2
     * @template B  
     * @param callable(E): Effect<R, E2, B> $f
     * @return Effect<R, E2, A|B>
     */
    abstract public function catchAll(callable $f): Effect;
    
    // Static constructors
    
    /**
     * Create an effect that succeeds with the given value.
     * 
     * @template A
     * @param A $value
     * @return Effect<never, never, A>
     */
    public static function succeed(mixed $value): Effect
    {
        return new SucceedEffect($value);
    }
    
    /**
     * Create an effect that fails with the given error.
     * 
     * @template E
     * @param E $error
     * @return Effect<never, E, never>
     */
    public static function fail(mixed $error): Effect
    {
        return new FailEffect($error);
    }
    
    /**
     * Create an effect from a synchronous computation.
     * 
     * @template A
     * @param callable(): A $computation
     * @return Effect<never, never, A>
     */
    public static function sync(callable $computation): Effect
    {
        return new SyncEffect($computation);
    }
    
    /**
     * Create an effect from an asynchronous computation.
     * 
     * @template A
     * @param callable(): A $computation  
     * @return Effect<never, never, A>
     */
    public static function async(callable $computation): Effect
    {
        return new AsyncEffect($computation);
    }
}
```

#### 1.2 Core Algebraic Data Types
```php
<?php declare(strict_types=1);

/**
 * Represents an optional value that may or may not be present.
 * 
 * @template A
 * @psalm-immutable
 */
enum Option
{
    case Some;
    case None;
    
    /**
     * @template T
     * @param T $value
     * @return Option<T>
     */
    public static function of(mixed $value): self
    {
        return $value !== null ? self::Some : self::None;
    }
    
    /**
     * @template T
     * @param T $value
     * @return Option<T>
     */
    public static function some(mixed $value): self
    {
        return self::Some;
    }
    
    /**
     * @return Option<never>
     */
    public static function none(): self
    {
        return self::None;
    }
    
    /**
     * @template B
     * @param callable(A): B $f
     * @return Option<B>
     */
    public function map(callable $f): self
    {
        return match($this) {
            self::Some => self::some($f($this->getValue())),
            self::None => self::none()
        };
    }
    
    /**
     * @template B
     * @param callable(A): Option<B> $f
     * @return Option<B>
     */
    public function flatMap(callable $f): self
    {
        return match($this) {
            self::Some => $f($this->getValue()),
            self::None => self::none()
        };
    }
    
    /**
     * @return A
     * @throws \RuntimeException when None
     */
    public function getValue(): mixed
    {
        return match($this) {
            self::Some => $this->value,
            self::None => throw new \RuntimeException('Cannot get value from None')
        };
    }
    
    /**
     * @param A $default
     * @return A
     */
    public function getOrElse(mixed $default): mixed
    {
        return match($this) {
            self::Some => $this->getValue(),
            self::None => $default
        };
    }
    
    private mixed $value = null;
}

/**
 * Represents a value that can be one of two types.
 * 
 * @template L - Left type (typically error)
 * @template R - Right type (typically success)  
 * @psalm-immutable
 */
enum Either
{
    case Left;
    case Right;
    
    /**
     * @template L
     * @param L $value
     * @return Either<L, never>
     */
    public static function left(mixed $value): self
    {
        $either = self::Left;
        $either->value = $value;
        return $either;
    }
    
    /**
     * @template R  
     * @param R $value
     * @return Either<never, R>
     */
    public static function right(mixed $value): self
    {
        $either = self::Right;
        $either->value = $value;
        return $either;
    }
    
    /**
     * @template R2
     * @param callable(R): R2 $f
     * @return Either<L, R2>
     */
    public function map(callable $f): self
    {
        return match($this) {
            self::Right => self::right($f($this->value)),
            self::Left => $this
        };
    }
    
    /**
     * @template R2
     * @param callable(R): Either<L, R2> $f  
     * @return Either<L, R2>
     */
    public function flatMap(callable $f): self
    {
        return match($this) {
            self::Right => $f($this->value),
            self::Left => $this
        };
    }
    
    public function isLeft(): bool
    {
        return $this === self::Left;
    }
    
    public function isRight(): bool
    {
        return $this === self::Right;
    }
    
    private mixed $value = null;
}

/**
 * Represents the result of an Effect computation.
 * 
 * @template E - Error type
 * @template A - Success type
 * @psalm-immutable  
 */
enum Exit
{
    case Success;
    case Failure;
    case Interrupted;
    
    /**
     * @template A
     * @param A $value
     * @return Exit<never, A>
     */
    public static function succeed(mixed $value): self
    {
        $exit = self::Success;
        $exit->value = $value;
        return $exit;
    }
    
    /**
     * @template E
     * @param E $error
     * @return Exit<E, never>
     */
    public static function fail(mixed $error): self
    {
        $exit = self::Failure;
        $exit->cause = new Cause($error);
        return $exit;
    }
    
    /**
     * @return Exit<never, never>
     */
    public static function interrupt(): self
    {
        $exit = self::Interrupted;
        $exit->cause = new Cause(null, interrupted: true);
        return $exit;
    }
    
    /**
     * @template B
     * @param callable(A): B $f
     * @return Exit<E, B>
     */
    public function map(callable $f): self
    {
        return match($this) {
            self::Success => self::succeed($f($this->value)),
            default => $this
        };
    }
    
    public function isSuccess(): bool { return $this === self::Success; }
    public function isFailure(): bool { return $this === self::Failure; }
    public function isInterrupted(): bool { return $this === self::Interrupted; }
    
    private mixed $value = null;
    private ?Cause $cause = null;
}

/**
 * Represents the cause of an Effect failure.
 * 
 * @template E
 * @psalm-immutable
 */
final readonly class Cause
{
    /**
     * @param E $error
     */
    public function __construct(
        public mixed $error,
        public ?string $trace = null,
        public bool $interrupted = false,
        public array $suppressedErrors = []
    ) {}
    
    /**
     * @template E2
     * @param callable(E): E2 $f
     * @return Cause<E2>
     */
    public function map(callable $f): self
    {
        return new self(
            $f($this->error),
            $this->trace,
            $this->interrupted,
            $this->suppressedErrors
        );
    }
    
    public function prettyPrint(): string
    {
        $output = "Error: " . $this->errorToString($this->error) . "\n";
        
        if ($this->trace !== null) {
            $output .= "Trace: " . $this->trace . "\n";
        }
        
        if ($this->interrupted) {
            $output .= "Interrupted: true\n";
        }
        
        return $output;
    }
    
    private function errorToString(mixed $error): string
    {
        return match(true) {
            $error instanceof \Throwable => $error->getMessage(),
            is_string($error) => $error,
            default => json_encode($error) ?: 'Unknown error'
        };
    }
}
```

#### 1.3 Type Utilities
```php
<?php declare(strict_types=1);

/**
 * Trait for implementing value-based equality.
 */
interface Equal
{
    public function equals(mixed $other): bool;
}

/**
 * Trait for implementing ordering relationships.
 */
interface Order extends Equal
{
    /**
     * @return int Negative if $this < $other, 0 if equal, positive if $this > $other
     */
    public function compare(mixed $other): int;
}

/**
 * Trait for implementing stable hashing.
 */
interface Hash
{
    public function hash(): string;
}

/**
 * Utility for branded types in PHP.
 * 
 * @template T
 */
trait BrandedType
{
    /**
     * @param mixed $value
     * @return static
     */
    abstract public static function of(mixed $value): static;
    
    /**
     * @return T
     */
    abstract public function unwrap(): mixed;
    
    final public function equals(mixed $other): bool
    {
        return $other instanceof static 
            && $this->unwrap() === $other->unwrap();
    }
}

/**
 * Example branded type for user IDs.
 */
final readonly class UserId
{
    use BrandedType;
    
    private function __construct(private int $value) {}
    
    public static function of(mixed $value): static
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('UserId must be positive integer');
        }
        return new self($value);
    }
    
    public function unwrap(): int
    {
        return $this->value;
    }
}
```

**Dependencies**: Phase 0  
**Technical Challenges**:
- PHP can't enforce generic constraints at runtime  
- Enum properties are experimental - may need readonly classes
- Type inference limited compared to TypeScript

**Success Criteria**:
- All ADTs work with Psalm level 9
- Basic Effect composition compiles and runs
- Performance overhead < 20% vs native PHP
