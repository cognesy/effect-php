
### Module 5: Service Layer & Dependency Injection

#### 5.1 Layer System Implementation
```php
<?php declare(strict_types=1);

namespace EffectPHP\Layer;

/**
 * A Layer describes how to construct a service with its dependencies.
 * 
 * @template R - Dependencies required to build this layer
 * @template E - Errors that can occur during construction
 * @template A - The service type this layer provides
 */
abstract readonly class Layer
{
    /**
     * Build this layer given its dependencies.
     * 
     * @param mixed $dependencies
     * @return Effect<never, E, A>
     */
    abstract public function build(mixed $dependencies): Effect;
    
    /**
     * Combine this layer with another layer.
     * 
     * @template R2, E2, B
     * @param Layer<R2, E2, B> $other
     * @return Layer<R&R2, E|E2, A&B>
     */
    public function and(Layer $other): Layer
    {
        return new CombinedLayer($this, $other);
    }
    
    /**
     * Compose this layer with another layer that depends on this layer's output.
     * 
     * @template R2, E2, B
     * @param Layer<A&R2, E2, B> $other
     * @return Layer<R&R2, E|E2, B>
     */
    public function compose(Layer $other): Layer
    {
        return new ComposedLayer($this, $other);
    }
    
    /**
     * Create a layer from a simple constructor function.
     * 
     * @template R, E, A
     * @param callable(R): A $constructor
     * @return Layer<R, never, A>
     */
    public static function fromFunction(callable $constructor): Layer
    {
        return new FunctionLayer($constructor);
    }
    
    /**
     * Create a layer from an Effect.
     * 
     * @template R, E, A
     * @param Effect<R, E, A> $effect
     * @return Layer<R, E, A>
     */
    public static function fromEffect(Effect $effect): Layer
    {
        return new EffectLayer($effect);
    }
    
    /**
     * Create a layer that provides a constant value.
     * 
     * @template A
     * @param A $value
     * @return Layer<never, never, A>
     */
    public static function succeed(mixed $value): Layer
    {
        return new SucceedLayer($value);
    }
}

/**
 * Service interface with tagged identification.
 * 
 * @template A
 */
interface Service
{
    public static function tag(): string;
}

/**
 * Service registry for managing service instances.
 */
final class ServiceRegistry
{
    /** @var array<string, mixed> */
    private array $services = [];
    
    /**
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @param A $instance
     */
    public function register(string $serviceClass, mixed $instance): void
    {
        $this->services[$serviceClass::tag()] = $instance;
    }
    
    /**
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @return A
     * @throws ServiceNotFoundException
     */
    public function get(string $serviceClass): mixed
    {
        $tag = $serviceClass::tag();
        
        if (!isset($this->services[$tag])) {
            throw new ServiceNotFoundException("Service not found: $tag");
        }
        
        return $this->services[$tag];
    }
    
    /**
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @return bool
     */
    public function has(string $serviceClass): bool
    {
        return isset($this->services[$serviceClass::tag()]);
    }
}

/**
 * Environment providing access to services.
 */
final readonly class Environment
{
    public function __construct(
        private ServiceRegistry $registry
    ) {}
    
    /**
     * Access a service from the environment.
     * 
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @return Effect<never, ServiceNotFoundException, A>
     */
    public function service(string $serviceClass): Effect
    {
        return Effect::sync(fn() => $this->registry->get($serviceClass));
    }
    
    /**
     * Access a service if available.
     * 
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @return Effect<never, never, Option<A>>
     */
    public function serviceOption(string $serviceClass): Effect
    {
        return Effect::sync(function() use ($serviceClass) {
            return $this->registry->has($serviceClass)
                ? Option::some($this->registry->get($serviceClass))
                : Option::none();
        });
    }
    
    /**
     * Create a new environment with an additional service.
     * 
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @param A $instance
     * @return Environment
     */
    public function withService(string $serviceClass, mixed $instance): self
    {
        $newRegistry = clone $this->registry;
        $newRegistry->register($serviceClass, $instance);
        return new self($newRegistry);
    }
}
```

#### 5.2 PSR-11 Container Bridge
```php
<?php declare(strict_types=1);

use Psr\Container\ContainerInterface;

/**
 * PSR-11 Container implementation using EffectPHP Layers.
 */
final class LayerContainer implements ContainerInterface
{
    private ServiceRegistry $registry;
    private array $layers = [];
    
    public function __construct()
    {
        $this->registry = new ServiceRegistry();
    }
    
    /**
     * Register a layer for a service.
     * 
     * @template A
     * @param class-string<Service<A>> $serviceClass
     * @param Layer<mixed, mixed, A> $layer
     */
    public function addLayer(string $serviceClass, Layer $layer): void
    {
        $this->layers[$serviceClass::tag()] = $layer;
    }
    
    public function get(string $id): mixed
    {
        // Check if already built
        if ($this->registry->has($id)) {
            return $this->registry->get($id);
        }
        
        // Build from layer
        if (!isset($this->layers[$id])) {
            throw new ServiceNotFoundException("Service not found: $id");
        }
        
        $layer = $this->layers[$id];
        $runtime = new Runtime();
        
        try {
            $environment = new Environment($this->registry);
            $buildEffect = $layer->build($environment);
            $service = $runtime->run($buildEffect);
            
            // Cache the built service
            $this->registry->register($id, $service);
            
            return $service;
        } catch (\Throwable $e) {
            throw new ServiceConstructionException(
                "Failed to construct service: $id",
                previous: $e
            );
        }
    }
    
    public function has(string $id): bool
    {
        return $this->registry->has($id) || isset($this->layers[$id]);
    }
}

/**
 * Example service implementations.
 */

// Database service
interface DatabaseService extends Service
{
    public static function tag(): string { return 'database'; }
    public function query(string $sql, array $params = []): Effect;
}

final readonly class PDODatabaseService implements DatabaseService
{
    public function __construct(
        private \PDO $pdo
    ) {}
    
    public static function tag(): string { return 'database'; }
    
    public function query(string $sql, array $params = []): Effect
    {
        return Effect::sync(function() use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        });
    }
}

// Layer for building database service
final class DatabaseLayer extends Layer
{
    public function __construct(
        private readonly string $dsn,
        private readonly string $username,
        private readonly string $password
    ) {}
    
    public function build(mixed $dependencies): Effect
    {
        return Effect::sync(function() {
            $pdo = new \PDO($this->dsn, $this->username, $this->password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return new PDODatabaseService($pdo);
        });
    }
}

// User repository service
interface UserRepository extends Service
{
    public static function tag(): string { return 'user_repository'; }
    public function findById(int $id): Effect;
    public function save(User $user): Effect;
}

final readonly class DatabaseUserRepository implements UserRepository
{
    public function __construct(
        private DatabaseService $database
    ) {}
    
    public static function tag(): string { return 'user_repository'; }
    
    public function findById(int $id): Effect
    {
        return $this->database
            ->query('SELECT * FROM users WHERE id = ?', [$id])
            ->map(function(array $rows) {
                if (empty($rows)) {
                    throw new UserNotFoundException("User not found: $id");
                }
                return User::fromArray($rows[0]);
            });
    }
    
    public function save(User $user): Effect
    {
        return $this->database
            ->query(
                'INSERT INTO users (name, email) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email)',
                [$user->name, $user->email]
            )
            ->map(fn() => $user);
    }
}

// Layer composition example
function buildApplicationLayers(): Layer
{
    $databaseLayer = new DatabaseLayer(
        'mysql:host=localhost;dbname=app',
        'username',
        'password'
    );
    
    $userRepositoryLayer = Layer::fromFunction(
        fn(Environment $env) => new DatabaseUserRepository(
            $env->service(DatabaseService::class)
        )
    );
    
    return $databaseLayer->and($userRepositoryLayer);
}
```

**Dependencies**: Phase 3  
**Technical Challenges**:
- Complex type inference for service dependencies
- Performance impact of runtime service resolution
- Error handling in dependency construction

**Success Criteria**:
- Type-safe dependency injection works in practice
- Error accumulation enables batch validation
- PSR-11 bridge integrates with existing PHP frameworks
