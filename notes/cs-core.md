# Effect-PHP Core Cheatsheet

## Eff - The Effect Factory

- `Eff::succeed(mixed $value): Effect<never, never, A>` - Lift a pure value.
- `Eff::fail(Throwable $error): Effect<never, E, never>` - Create a failed effect.
- `Eff::sync(callable(): A $computation): Effect<never, Throwable, A>` - Lift a synchronous computation.
- `Eff::async(callable(callable): void $register): Effect<never, Throwable, A>` - Lift an async computation.
- `Eff::service(string $serviceTag): Effect<R, ServiceNotFoundException, T>` - Access a service from the context.
- `Eff::clock(): Effect<Clock, ServiceNotFoundException, Clock>` - Access the Clock service.
- `Eff::currentTimeMillis(): Effect<Clock, ServiceNotFoundException, int>` - Get current time.
- `Eff::clockWith(callable(Clock): Effect $f): Effect<Clock, ServiceNotFoundException, A>` - Use the Clock service.
- `Eff::allInParallel(array $effects): Effect<R, E, A[]>` - Run effects in parallel.
- `Eff::raceAll(array $effects): Effect<R, E, A>` - Race multiple effects.
- `Eff::sleepFor(Duration $duration): Effect<Clock, never, void>` - Sleep for a duration.
- `Eff::never(): Effect<never, never, never>` - An effect that never completes.
- `Eff::when(bool $condition, Effect $effect): Effect<R, E, A|null>` - Conditional effect.
- `Eff::fromOption(Option $option, Throwable $whenEmpty): Effect<never, Throwable, A>` - Option to Effect.
- `Eff::fromEither(Either $either): Effect<never, L, R>` - Either to Effect.
- `Eff::scoped(callable(Scope): Effect $scoped): Effect<R, E, A>` - Create a scoped effect.
- `Run::sync(Effect $effect): A` - Execute an effect synchronously.
- `Run::syncResult(Effect $effect): Either<E, A>` - Execute an effect safely.

## Effect - The Core Interface

- `->map(callable(A): B): Effect<R, E, B>` - Transform the success value.
- `->flatMap(callable(A): Effect): Effect<R&R2, E|E2, B>` - Chain dependent computations.
- `->mapAsync(callable(A): B): Effect<R, E, B>` - Transform asynchronously.
- `->catchError(string|callable $type, callable $handler): Effect<R&R2, E2, A|A2>` - Handle errors.
- `->orElse(Effect $fallback): Effect<R&R2, E2, A|A2>` - Provide a fallback.
- `->whenSucceeds(callable $sideEffect): Effect<R, E, A>` - Peform a side-effect on success.
- `->ensuring(callable $cleanup): Effect<R, E, A>` - Ensure cleanup.
- `->timeoutAfter(Duration $timeout): Effect<R, E|TimeoutException, A>` - Add a timeout.
- `->retryWith(Schedule $schedule): Effect<R, E, A>` - Retry with a schedule.
- `->providedWith(Context $context): Effect<R&~RProvided, E, A>` - Provide dependencies.
- `->providedByLayer(Layer $layer): Effect<RLayer, E|ELayer, A>` - Provide a layer.
- `->withinScope(callable(Scope): Effect $scoped): Effect<R, E, B>` - Execute in a scope.
- `->zipWithPar(Effect ...$others): Effect<R, E, array{A, B}>` - Combine results in parallel.
- `->raceWith(Effect ...$competitors): Effect<R, E, A|B>` - Race effects.

## Option - Optional Values

- `Option::some(mixed $value): Option<A>` - A value is present.
- `Option::none(): Option<never>` - A value is absent.
- `->map(callable(A): B): Option<B>` - Map the value.
- `->flatMap(callable(A): Option<B>): Option<B>` - Chain optional computations.
- `->isSome(): bool` / `->isNone(): bool` - Check the state.
- `->whenNone(mixed $default): A` - Get value or default.
- `->otherwiseUse(Option $alternative): Option<A>` - Use an alternative option.
- `->toEffect(Throwable $whenEmpty): Effect<never, E, A>` - Convert to an Effect.

## Either - Left or Right

- `Either::left(mixed $value): Either<L, never>` - A value on the left (error).
- `Either::right(mixed $value): Either<never, R>` - A value on the right (success).
- `->map(callable(R): R2): Either<L, R2>` - Map the right value.
- `->mapLeft(callable(L): L2): Either<L2, R>` - Map the left value.
- `->flatMap(callable(R): Either): Either<L, R2>` - Chain computations.
- `->fold(callable $whenLeft, callable $whenRight): T` - Get a value out.
- `->toEffect(): Effect<never, L, R>` - Convert to an Effect.

## Layer - Declarative Services

- `Layer::fromEffect(Effect $effect, string $tag): Layer<RIn, E, ROut>` - Create from an effect.
- `Layer::fromFactory(callable $factory, string $tag): Layer<never, E, ROut>` - Create from a factory.
- `Layer::fromValue(object $service, string $tag): Layer<never, never, ROut>` - Create from a value.
- `->build(): Effect<RIn, E, Context<ROut>>` - Build the layer's context.
- `->combineWith(Layer $other): Layer<RIn & RIn2, E | E2, ROut & ROut2>` - Combine independent layers.
- `->andThen(Layer $other): Layer<RIn & RIn2, E | E2, ROut & ROut2>` - Combine dependent layers.
- `->provideTo(Effect $effect): Effect<RIn, E, A>` - Provide the layer to an effect.

## Scope - Resource Management

- `Scope::make(callable(Scope): Effect $scoped): Effect<R, E, A>` - Create a scoped effect.
- `->addFinalizer(callable(): Effect): Effect<never, never, null>` - Add a cleanup action.
- `->acquireResource(Effect $acquire, callable $release): Effect<R, E, A>` - Acquire a resource with cleanup.
- `->close(): Effect<never, Throwable, void>` - Close the scope and run finalizers.

## Schedule - Retry Policies

- `Schedule::once(): Schedule` - Run once.
- `Schedule::fixedDelay(Duration $delay): Schedule` - Fixed delay.
- `Schedule::exponentialBackoff(Duration $base, float $factor = 2.0): Schedule` - Exponential backoff.
- `Schedule::fibonacciBackoff(Duration $base): Schedule` - Fibonacci backoff.
- `Schedule::linearBackoff(Duration $base): Schedule` - Linear backoff.
- `->upToMaxRetries(int $times): Schedule` - Limit retries.
- `->upToMaxDuration(Duration $max): Schedule` - Limit duration.
- `->withJitter(float $factor = 0.1): Schedule` - Add jitter.

## Cause - Structured Errors

- `Cause::fail(Throwable $error): Fail` - A single failure.
- `Cause::interrupt(): Interrupt` - An interruption.
- `Cause::parallel(Cause[] $causes): Parallel` - Parallel failures.
- `Cause::sequential(Cause[] $causes): Sequential` - Sequential failures.
- `->and(Cause $other): Cause` - Combine causes.
- `->map(callable(E): E2): Cause<E2>` - Map the error.
- `->toException(): Throwable` - Convert to an exception.
- `->prettyPrint(): string` - Get a formatted string.
- `->contains(string $errorType): bool` - Check for an error type.

## Utils

- `Utils::tap(Effect $effect, callable(A): void $f): Effect` - Tap into a success value.
- `Utils::log(string $message): Effect` - Log a message.
