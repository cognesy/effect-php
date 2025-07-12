<?php declare(strict_types=1);

use EffectPHP\Core\Context;
use EffectPHP\Core\Fx;
use EffectPHP\Core\Layer;
use EffectPHP\Core\Managed;
use EffectPHP\Core\Runtimes\SyncRuntime;
use EffectPHP\Utils\Clock\Clock;
use EffectPHP\Utils\Clock\VirtualClock;

beforeEach(function () {
    $this->clock = new VirtualClock();
    $this->runtime = new SyncRuntime();
    $this->context = (new Context())->with(Clock::class, $this->clock);
});

test('Effect facade with SyncRuntime handles Pure, Service, and Sleep effects', function () {
    // Arrange: Define a service and layer
    $service = new class {
        public function greet(): string {
            return 'Hello, World!';
        }
    };
    $layer = Layer::succeed('Greeter', $service);

    // Create an effect program combining Pure, Service, and Sleep
    $program = Fx::value(42)
        ->then(Fx::service('Greeter')->map(fn($s) => $s->greet()))
        ->tap(Fx::sleep(1000))
        ->map(fn($greeting) => strtoupper($greeting));

    // Act: Run the program with the layer-provided context
    $result = $this->runtime
        ->withContext($this->context)
        ->run($program->provide($layer));

    // Assert: Verify result and clock advancement
    expect($result)->toBe('HELLO, WORLD!');
    expect($this->clock->currentTimeMillis())->toBe(1000);
});

test('Layer and Context composition with service dependencies', function () {
    // Arrange: Define two services with dependencies
    $serviceA = new class {
        public function getValue(): int {
            return 100;
        }
    };
    $serviceB = new class($serviceA) {
        private $serviceA;

        public function __construct($serviceA) {
            $this->serviceA = $serviceA;
        }

        public function compute(): int {
            return $this->serviceA->getValue() * 2;
        }
    };

    // Create layers for both services
    $layerA = Layer::succeed('ServiceA', $serviceA);
    $layerB = Layer::succeed('ServiceB', $serviceB);
    $composedLayer = $layerA->compose($layerB);

    // Create an effect program using the services
    $program = Fx::service('ServiceB')->map(fn($s) => $s->compute());

    // Act: Run the program with composed layer
    $result = $this->runtime
        ->withContext($this->context)
        ->run($program->provide($composedLayer));

    // Assert: Verify the computation result
    expect($result)->toBe(200);
});

test('Scope and Managed handle resource acquisition and release', function () {
    // Arrange: Track resource lifecycle
    $resourceLog = [];
    $acquire = function () use (&$resourceLog) {
        $resourceLog[] = 'acquired';
        return 'resource';
    };
    $release = function ($resource) use (&$resourceLog) {
        $resourceLog[] = 'released';
    };

    // Create a Managed resource
    $managed = Managed::from($acquire, $release);

    // Create a program that uses the managed resource in a scope
    $program = $managed->reserve()->map(
        fn($res) => strtoupper($res),
    );

    // Act: Run the program
    $result = $this->runtime->withContext($this->context)->run($program);

    // Assert: Verify result and resource lifecycle
    expect($result)->toBe('RESOURCE');
    expect($resourceLog)->toEqual(['acquired', 'released']);
});

test('Error handling with FailEffect and layered context', function () {
    // Arrange: Create a program that fails
    $program = Fx::fail(new \RuntimeException('Test error'))
        ->then(Fx::value('unreachable'));

    // Create a layer with a service
    $layer = Layer::succeed('DummyService', new class {
        public function dummy(): string {
            return 'dummy';
        }
    });

    // Act & Assert: Expect the exception to be thrown
    expect(fn() => $this->runtime
        ->withContext($this->context)
        ->run($program->provide($layer)),
    )->toThrow(\RuntimeException::class, 'Test error');
});