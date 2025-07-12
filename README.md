# Effect PHP

A port of [Effect-TS](https://effect.website/) functional programming library to PHP 8+. Provides composable, type-safe
effects for managing asynchronous operations, error handling, resource management, and dependency injection.

## Core Concepts

**Effect** - A description of a computation that may succeed, fail, or require dependencies. Effects are lazy and
composable.
**Runtime** - Executes effect programs using pluggable handlers for different capabilities (sync, async, fiber-based).
**Context** - Immutable service container providing dependency injection via type-safe service resolution.
**Layer** - Builds and composes service dependencies, analogous to dependency injection containers.
**Scope** - Manages resource lifecycle with automatic cleanup via finalizers.

## Defining Effect Programs

```php
use EffectPHP\Core\Fx;
use EffectPHP\Core\Runtimes\SyncRuntime;

// Pure values
$effect = Fx::succeed(42);
$effect = Fx::unit(); // null

// Suspend computations
$effect = Fx::suspend(fn() => expensive_operation());

// Async operations
$effect = Fx::async(function($resolve) {
    // async work
    $resolve($result);
});

// Service dependencies
$effect = Fx::service(DatabaseInterface::class);

// Composition via map/flatMap
$program = Fx::succeed(10)
    ->map(fn($x) => $x * 2)
    ->flatMap(fn($x) => Fx::succeed($x + 5));
```

## Error Handling

```php
use EffectPHP\Core\Fx;
use EffectPHP\Core\Runtimes\SyncRuntime;

// Explicit failures
$effect = Fx::fail(new RuntimeException("error"));

// Error handling is built into the runtime
try {
    $runtime = new SyncRuntime();
    $result = $runtime->run($effect);
} catch (Throwable $e) {
    // Handle errors
}

// Combinators for error recovery
$safe = $effect->orElse(Fx::succeed("fallback"));
```

## Dependency Injection with Layers

```php
use EffectPHP\Core\Fx;
use EffectPHP\Core\Layer;
use EffectPHP\Core\Context;

// Define services
$dbLayer = Layer::provides(DatabaseInterface::class, new PDODatabase());
$logLayer = Layer::providesFrom(LoggerInterface::class, 
    fn($ctx) => new Logger($ctx->get(ConfigInterface::class))
);

// Compose layers
$appLayer = $dbLayer->dependsOn($logLayer);

// Provide dependencies
$program = Fx::service(DatabaseInterface::class)
    ->flatMap(fn($db) => Fx::succeed($db->query("SELECT 1")))
    ->provide($appLayer);
```

## Resource Management

```php
use EffectPHP\Core\Managed;

// Managed resources with automatic cleanup
$managed = Managed::from(
    acquire: fn() => fopen("file.txt", "r"),
    release: fn($handle) => fclose($handle)
);

$program = $managed->reserve()
    ->flatMap(fn($file) => Effect::succeed(fread($file, 1024)));

// Resource automatically closed when scope ends
```

## Runtime Execution

```php
use EffectPHP\Core\Runtimes\SyncRuntime;

$runtime = new SyncRuntime();

// Execute single effect
$result = $runtime->run($program);

// Execute multiple effects
$results = $runtime->runAll($effect1, $effect2, $effect3);

// Custom runtime with context
$runtime = new SyncRuntime(
    handlers: [], // custom handlers
    context: Context::empty()->with(ConfigInterface::class, $config)
);
```

## Key Features

- **Composable**: Chain operations with `map`, `flatMap`, `then`, `tap`
- **Type-safe**: Leverages PHP 8+ type system and generics via PHPStan
- **Lazy**: Effects are descriptions, not eager computations
- **Resource-safe**: Automatic cleanup via scoped resource management
- **Testable**: Pure functions and dependency injection enable easy testing
- **Extensible**: Custom effect types and handlers for domain-specific needs

## Testing

```bash
composer test
```

## Packages

- `core` - Core effect system and runtime
- `utils` - Utility types (Either, Option, Result, Duration, etc.)
- `schema` - Schema validation and serialization
- `collection` - Immutable collections with effect integration
- `promise` - Promise abstractions for async runtimes
- `stream` - Streaming data processing
- `validation` - Data validation effects
- `runtime-*` - Runtime implementations (amphp, react, swoole, etc.)

## Requirements

- PHP 8.1+
- Composer

## License

MIT