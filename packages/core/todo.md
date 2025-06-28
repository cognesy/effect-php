1. Effect.try Constructor
php// Current: Only Eff::sync (assumes no errors)
Eff::sync(callable(): A $computation): Effect<never, Throwable, A>

// Missing: try constructor for operations that might throw
Eff::try(callable(): A $computation): Effect<never, UnknownException, A>
Eff::try(callable(): A $computation, callable $catch): Effect<never, E, A>
Why critical: Handles synchronous operations that might fail (like JSON parsing), which is extremely common.
2. Effect.promise Constructor
php// Missing: For async operations guaranteed to succeed
Eff::promise(callable(): Promise $computation): Effect<never, never, A>
3. Effect.tryPromise Constructor
php// Missing: For async operations that might fail
Eff::tryPromise(callable(): Promise $computation): Effect<never, UnknownException, A>
Eff::tryPromise(callable(): Promise $computation, callable $catch): Effect<never, E, A>
4. Effect.suspend Constructor
php// Missing: Critical for lazy evaluation and preventing stack overflow
Eff::suspend(callable(): Effect $computation): Effect<R, E, A>
Why critical: Prevents immediate execution, handles circular dependencies, and enables proper recursive effects.

php// Current limited approach
try {
    $result = Run::sync(
        Eff::sync(fn() => json_decode($json, true, 512, JSON_THROW_ON_ERROR))
    );
} catch (Exception $e) {
    // Manual error handling
}

// What you should be able to do
$program = Eff::try(
    try: fn() => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
    catch: fn($e) => new JsonParseError("Invalid JSON: " . $e->getMessage())
)->flatMap(fn($data) => 
    processData($data)
)->catchTag('JsonParseError', fn($e) => 
    Eff::succeed(['error' => $e->getMessage()])
);
