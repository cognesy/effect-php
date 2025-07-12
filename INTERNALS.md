# EffectPHP Internals

## Overview

EffectPHP is a PHP library designed to facilitate functional programming paradigms through a lazy effect system. It
allows developers to define computations as immutable data structures, which can be composed and executed in a
controlled manner. The library supports both synchronous and asynchronous execution models, enabling flexible
application architectures.

It is inspired by the Effect-TS library and aims to provide a robust foundation for building complex applications with
clear separation of concerns, dependency injection, and resource management.

## Code Structure

The library is maintained as a monorepo with the following key directories:

- 'packages/core' - Core effect system and runtime implementations
- 'packages/utils' - Utility functions and types for common operations

Each package follows the same directory structure:

- 'src' - Source code
- 'tests' - Unit and integration tests
- 'vendor' - Third-party dependencies managed by Composer (ignore it)

## Core Architecture

EffectPHP implements a lazy functional effect system using a **handler-based interpreter** pattern. Effects are
immutable data structures representing computations that are lazily evaluated by pluggable runtime engines.

### Effect System Foundation

**Effect Interface** (`Effect`) - Minimal contract defining composable computation descriptions with `map`, `flatMap`,
`then`, and `provide` operations for monadic composition.

**Fx Factory** (`Fx`) - Static constructor methods for creating primitive effects: `succeed`, `fail`, `suspend`,
`async`, `service`, `sleep`. Acts as the primary API entry point.

**Runtime State** (`RuntimeState`) - Immutable execution context containing:

- `Context` - Service container for dependency injection
- `ContinuationStack` - LIFO stack for managing computation continuations
- `Scope` - Resource lifecycle management with automatic cleanup
- `value` - Current computation result

### Handler Architecture

**EffectHandler Interface** - Strategy pattern for effect interpretation with `supports(Effect)` and
`handle(Effect, RuntimeState)` methods.

**Handler Registry** - Runtime maintains ordered collection of handlers. First handler that supports an effect type
processes it.

**Core Handlers**:

- `PureHandler` - Immediate values (`PureEffect`)
- `BindHandler` - Monadic composition (`BindEffect`)
- `SuspendHandler` - Lazy thunk evaluation (`SuspendEffect`)
- `ServiceHandler` - Dependency resolution (`ServiceEffect`)
- `ProvideHandler` - Context modification (`ProvideEffect`)
- `FailHandler` - Error propagation (`FailEffect`)
- `SleepHandler` - Synchronous delays (`SleepEffect`)
- `NoAsyncHandler` - Async rejection for sync runtime (`AsyncEffect`)

### Runtime Execution Model

**SyncRuntime** - Trampolined interpreter using continuation-passing style:

1. Start with effect and initial runtime state
2. Find compatible handler for current effect
3. Handler returns new runtime state with next computation
4. If result is another effect, loop; otherwise resume from continuation stack
5. Continue until stack empty (program complete) or exception thrown

**Trampoline Loop** - Prevents stack overflow by converting recursive calls to iterative execution with explicit
continuation management.

### Dependency Injection System

**Context** - Immutable type-indexed service container with `with(class, service)` for additions and `get(class)` for
retrieval. Supports merging with right-bias override semantics.

**Layer** - Composable dependency builder using closure-based factories:

- `Layer.succeed(class, service)` - Constant service provision
- `Layer.of(class, factory)` - Context-dependent service construction
- `compose(other)` - Sequential layer application
- `merge(other)` - Parallel layer combination with right-bias

### Resource Management

**Scope** - RAII-style resource lifecycle management with finalizer registration. Automatically closes managed resources
when scope ends or exceptions occur.

**Managed** - Resource acquisition/release patterns with automatic cleanup integration into scope system.

## Utils Package

### Continuation Management

**ContinuationStack** - Type-safe LIFO stack built on `SplStack` for managing computation continuations. Provides O(1)
push/pop operations essential for trampolined execution.

### Result Types

**Result** - Algebraic data type for structured success/failure handling with `Success` and `Failure` variants. Supports
monadic operations (`map`, `flatMap`, `fold`) and safe value extraction.

### Time Abstractions

**Duration** - Immutable time span representation with nanosecond precision. Supports arithmetic operations and
conversion to various time units including PHP's `DateInterval`.

**Clock System** - Pluggable time providers including `SystemClock` for real time and `VirtualClock` for testing with
controllable time progression.

### Exception Handling

**Specialized Exceptions**:

- `InterruptedException` - Computation cancellation
- `TimeoutException` - Operation timeout
- `CompositeException` - Multiple error aggregation

## Key Design Decisions

**Immutability** - All core data structures are immutable, enabling safe sharing and functional composition patterns.

**Handler Extensibility** - Runtime behavior is fully customizable through pluggable handlers, allowing domain-specific
effect types and execution strategies.

**Continuation-Passing Style** - Trampolined execution prevents stack overflow while maintaining functional composition
semantics.

**Type Safety** - Leverages PHP 8+ type system with extensive PHPDoc generics for compile-time safety via static
analysis tools.

**Resource Safety** - Scope-based resource management ensures automatic cleanup even in error scenarios.

## Runtime Variants

**SyncRuntime** - Immediate execution for synchronous operations with async rejection.

**Async Runtimes** - Pluggable async execution engines (ReactPHP, Amphp, Swoole) with event loop integration and
non-blocking I/O support.

The architecture enables transparent switching between synchronous and asynchronous execution modes without changing
effect program code, providing deployment flexibility and testing simplicity.