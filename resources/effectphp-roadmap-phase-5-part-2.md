
### Module 7: Framework Integration & PSR Compliance

#### 7.1 PSR-7 HTTP Integration
```php
<?php declare(strict_types=1);

namespace EffectPHP\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Convert PSR-7 requests to Effects and back.
 */
final class HttpEffect
{
    /**
     * Create an Effect from a PSR-7 request.
     * 
     * @return Effect<ServerRequestInterface, never, ServerRequestInterface>
     */
    public static function fromRequest(ServerRequestInterface $request): Effect
    {
        return Effect::succeed($request);
    }
    
    /**
     * Convert an Effect result to a PSR-7 response.
     * 
     * @template A
     * @param Effect<mixed, mixed, A> $effect
     * @param callable(A): ResponseInterface $successHandler
     * @param callable(mixed): ResponseInterface $errorHandler
     * @return ResponseInterface
     */
    public static function toResponse(
        Effect $effect,
        callable $successHandler,
        callable $errorHandler
    ): ResponseInterface {
        $runtime = new Runtime();
        $exit = $runtime->runExit($effect);
        
        return Match::exit(
            $exit,
            onSuccess: $successHandler,
            onFailure: $errorHandler,
            onInterruption: fn() => $errorHandler('Request interrupted')
        );
    }
}

/**
 * PSR-15 middleware for Effect-based request handling.
 */
final readonly class EffectMiddleware implements MiddlewareInterface
{
    public function __construct(
        private \Closure $effectHandler
    ) {}
    
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $effect = HttpEffect::fromRequest($request)
            ->flatMap($this->effectHandler)
            ->catchAll(fn($error) => Effect::succeed($this->handleError($error)));
        
        return HttpEffect::toResponse(
            $effect,
            successHandler: fn($response) => $response,
            errorHandler: fn($error) => $this->handleError($error)
        );
    }
    
    private function handleError(mixed $error): ResponseInterface
    {
        $statusCode = match(true) {
            $error instanceof \InvalidArgumentException => 400,
            $error instanceof \UnauthorizedAccessException => 401,
            $error instanceof \NotFoundException => 404,
            default => 500
        };
        
        return new \Laminas\Diactoros\Response\JsonResponse([
            'error' => $error instanceof \Throwable ? $error->getMessage() : (string)$error,
            'code' => $statusCode
        ], $statusCode);
    }
}

/**
 * Effect-based router.
 */
final class EffectRouter
{
    /** @var array<string, array<string, \Closure>> */
    private array $routes = [];
    
    /**
     * Register a GET route.
     * 
     * @param string $path
     * @param callable(ServerRequestInterface): Effect $handler
     */
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }
    
    /**
     * Register a POST route.
     * 
     * @param string $path
     * @param callable(ServerRequestInterface): Effect $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }
    
    /**
     * Route a request to the appropriate handler.
     * 
     * @param ServerRequestInterface $request
     * @return Effect<mixed, NotFoundException, ResponseInterface>
     */
    public function route(ServerRequestInterface $request): Effect
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        if (!isset($this->routes[$method][$path])) {
            return Effect::fail(new NotFoundException("Route not found: $method $path"));
        }
        
        $handler = $this->routes[$method][$path];
        return Effect::sync(fn() => $handler($request));
    }
}
```

#### 7.2 Laravel Integration
```php
<?php declare(strict_types=1);

namespace EffectPHP\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Laravel service provider for EffectPHP.
 */
final class EffectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Runtime::class, function() {
            return new Runtime(new RuntimeConfig(
                maxConcurrency: config('effect.max_concurrency', 1000),
                defaultTimeout: config('effect.default_timeout', 30.0),
                enableTracing: config('effect.enable_tracing', false),
                enableMetrics: config('effect.enable_metrics', false)
            ));
        });
        
        $this->app->bind(EffectContract::class, EffectService::class);
        
        // Register Effect-based container
        $this->app->singleton(LayerContainer::class, function() {
            $container = new LayerContainer();
            
            // Auto-register Laravel services as Effect layers
            $container->addLayer(
                DatabaseService::class,
                Layer::fromFunction(fn() => new LaravelDatabaseService(app('db')))
            );
            
            return $container;
        });
    }
    
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/effect.php' => config_path('effect.php'),
        ]);
        
        // Register route macros
        Route::macro('effect', function(string $uri, callable $handler) {
            return Route::any($uri, function(\Illuminate\Http\Request $request) use ($handler) {
                $effectHandler = function(ServerRequestInterface $psrRequest) use ($handler) {
                    return $handler($psrRequest);
                };
                
                $middleware = new EffectMiddleware($effectHandler);
                $psrRequest = \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory::createRequest($request);
                
                return $middleware->process($psrRequest, new NullRequestHandler());
            });
        });
    }
}

/**
 * Laravel-specific Effect utilities.
 */
final class LaravelEffects
{
    /**
     * Create an Effect from a Laravel model query.
     * 
     * @template T
     * @param \Illuminate\Database\Eloquent\Builder<T> $query
     * @return Effect<never, \Illuminate\Database\Eloquent\ModelNotFoundException, T>
     */
    public static function fromEloquent(\Illuminate\Database\Eloquent\Builder $query): Effect
    {
        return Effect::sync(fn() => $query->firstOrFail());
    }
    
    /**
     * Create an Effect from a Laravel job.
     * 
     * @param class-string $jobClass
     * @param array $parameters
     * @return Effect<never, never, mixed>
     */
    public static function dispatchJob(string $jobClass, array $parameters = []): Effect
    {
        return Effect::async(function() use ($jobClass, $parameters) {
            $job = new $jobClass(...$parameters);
            dispatch($job);
            return null;
        });
    }
    
    /**
     * Create an Effect for Laravel validation.
     * 
     * @param array $data
     * @param array $rules
     * @return Effect<never, \Illuminate\Validation\ValidationException, array>
     */
    public static function validate(array $data, array $rules): Effect
    {
        return Effect::sync(function() use ($data, $rules) {
            $validator = validator($data, $rules);
            
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
            
            return $validator->validated();
        });
    }
}

// Usage example in Laravel controller:
final class UserController
{
    public function show(int $id): ResponseInterface
    {
        $effect = LaravelEffects::fromEloquent(User::where('id', $id))
            ->map(fn(User $user) => new UserResource($user))
            ->map(fn(UserResource $resource) => response()->json($resource->toArray()));
        
        return HttpEffect::toResponse(
            $effect,
            successHandler: fn($response) => $response,
            errorHandler: fn($error) => response()->json(['error' => $error->getMessage()], 404)
        );
    }
}
```

#### 7.3 Symfony Integration
```php
<?php declare(strict_types=1);

namespace EffectPHP\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for EffectPHP.
 */
final class EffectBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new EffectLayerPass());
        $container->addCompilerPass(new EffectServicePass());
    }
}

/**
 * Symfony-specific Effects utilities.
 */
final class SymfonyEffects
{
    /**
     * Create an Effect from a Symfony form.
     * 
     * @param \Symfony\Component\Form\FormInterface $form
     * @return Effect<never, \Symfony\Component\Form\FormError, array>
     */
    public static function fromForm(\Symfony\Component\Form\FormInterface $form): Effect
    {
        return Effect::sync(function() use ($form) {
            if (!$form->isSubmitted() || !$form->isValid()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                throw new FormValidationException($errors);
            }
            
            return $form->getData();
        });
    }
    
    /**
     * Create an Effect for Doctrine operations.
     * 
     * @template T
     * @param callable(): T $operation
     * @return Effect<never, \Doctrine\DBAL\Exception, T>
     */
    public static function doctrine(callable $operation): Effect
    {
        return Effect::sync($operation)
            ->catchAll(function(\Throwable $e) {
                if ($e instanceof \Doctrine\DBAL\Exception) {
                    return Effect::fail($e);
                }
                throw $e;
            });
    }
}
```

**Dependencies**: All previous phases  
**Technical Challenges**:
- Collection performance vs native PHP arrays
- Framework integration complexity
- Maintaining PSR compliance

**Success Criteria**:
- Collections perform within acceptable bounds
- Seamless integration with Laravel and Symfony
- Full PSR-7/15 compliance
