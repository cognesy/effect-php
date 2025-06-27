<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Option;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;

interface TestService 
{
    public function getValue(): string;
}

class TestServiceImpl implements TestService
{
    public function __construct(private string $value) {}
    
    public function getValue(): string 
    {
        return $this->value;
    }
}

class AnotherService
{
    public function __construct(public int $number) {}
}

describe('Context', function () {
    
    describe('empty', function () {
        it('creates empty context', function () {
            $context = Context::empty();
            
            expect($context)->toBeInstanceOf(Context::class)
                ->and($context->hasService('NonExistent'))->toBeFalse();
        });
    });
    
    describe('withService', function () {
        it('adds service to context', function () {
            $service = new TestServiceImpl('test value');
            $context = Context::empty()->withService(TestService::class, $service);
            
            expect($context->hasService(TestService::class))->toBeTrue()
                ->and($context->getService(TestService::class))->toBe($service);
        });
        
        it('creates new context instance', function () {
            $original = Context::empty();
            $service = new TestServiceImpl('test');
            $withService = $original->withService(TestService::class, $service);
            
            expect($withService)->not->toBe($original)
                ->and($original->hasService(TestService::class))->toBeFalse()
                ->and($withService->hasService(TestService::class))->toBeTrue();
        });
        
        it('chains multiple services', function () {
            $service1 = new TestServiceImpl('test');
            $service2 = new AnotherService(42);
            
            $context = Context::empty()
                ->withService(TestService::class, $service1)
                ->withService(AnotherService::class, $service2);
            
            expect($context->hasService(TestService::class))->toBeTrue()
                ->and($context->hasService(AnotherService::class))->toBeTrue()
                ->and($context->getService(TestService::class))->toBe($service1)
                ->and($context->getService(AnotherService::class))->toBe($service2);
        });
    });
    
    describe('getService', function () {
        it('retrieves existing service', function () {
            $service = new TestServiceImpl('hello');
            $context = Context::empty()->withService(TestService::class, $service);
            
            $retrieved = $context->getService(TestService::class);
            
            expect($retrieved)->toBe($service)
                ->and($retrieved->getValue())->toBe('hello');
        });
        
        it('throws exception for missing service', function () {
            $context = Context::empty();
            
            expect(fn() => $context->getService('MissingService'))
                ->toThrow(ServiceNotFoundException::class);
        });
    });
    
    describe('hasService', function () {
        it('returns true for existing services', function () {
            $service = new TestServiceImpl('test');
            $context = Context::empty()->withService(TestService::class, $service);
            
            expect($context->hasService(TestService::class))->toBeTrue();
        });
        
        it('returns false for missing services', function () {
            $context = Context::empty();
            
            expect($context->hasService('MissingService'))->toBeFalse();
        });
    });
    
    describe('mergeWith', function () {
        it('combines two contexts', function () {
            $service1 = new TestServiceImpl('first');
            $service2 = new AnotherService(123);
            
            $context1 = Context::empty()->withService(TestService::class, $service1);
            $context2 = Context::empty()->withService(AnotherService::class, $service2);
            
            $merged = $context1->mergeWith($context2);
            
            expect($merged->hasService(TestService::class))->toBeTrue()
                ->and($merged->hasService(AnotherService::class))->toBeTrue()
                ->and($merged->getService(TestService::class))->toBe($service1)
                ->and($merged->getService(AnotherService::class))->toBe($service2);
        });
        
        it('overwrites conflicting services with right-hand values', function () {
            $service1 = new TestServiceImpl('first');
            $service2 = new TestServiceImpl('second');
            
            $context1 = Context::empty()->withService(TestService::class, $service1);
            $context2 = Context::empty()->withService(TestService::class, $service2);
            
            $merged = $context1->mergeWith($context2);
            
            expect($merged->getService(TestService::class))->toBe($service2)
                ->and($merged->getService(TestService::class)->getValue())->toBe('second');
        });
    });
    
    describe('findService', function () {
        it('returns Some for existing service', function () {
            $service = new TestServiceImpl('found');
            $context = Context::empty()->withService(TestService::class, $service);
            
            $result = $context->findService(TestService::class);
            
            expect($result)->toBeInstanceOf(Option::class)
                ->and($result->isSome())->toBeTrue()
                ->and($result->whenNone(null))->toBe($service);
        });
        
        it('returns None for missing service', function () {
            $context = Context::empty();
            
            $result = $context->findService('MissingService');
            
            expect($result)->toBeInstanceOf(Option::class)
                ->and($result->isNone())->toBeTrue();
        });
    });
});

describe('Layer', function () {
    
    describe('fromValue', function () {
        it('creates layer from service instance', function () {
            $service = new TestServiceImpl('layer value');
            $layer = Layer::fromValue($service, TestService::class);
            
            expect($layer)->toBeInstanceOf(Layer::class);
            
            $context = runEffect($layer->build());
            expect($context->hasService(TestService::class))->toBeTrue()
                ->and($context->getService(TestService::class))->toBe($service);
        });
    });
    
    describe('fromFactory', function () {
        it('creates layer from factory function', function () {
            $factory = fn() => new TestServiceImpl('factory created');
            $layer = Layer::fromFactory($factory, TestService::class);
            
            $context = runEffect($layer->build());
            $service = $context->getService(TestService::class);
            
            expect($service)->toBeInstanceOf(TestServiceImpl::class)
                ->and($service->getValue())->toBe('factory created');
        });
        
        it('handles factory exceptions', function () {
            $factory = fn() => throw new \RuntimeException('Factory failed');
            $layer = Layer::fromFactory($factory, TestService::class);
            
            expect($layer->build())->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('fromEffect', function () {
        it('creates layer from effect computation', function () {
            $effect = Eff::sync(fn() => new TestServiceImpl('effect created'));
            $layer = Layer::fromEffect($effect, TestService::class);
            
            $context = runEffect($layer->build());
            $service = $context->getService(TestService::class);
            
            expect($service->getValue())->toBe('effect created');
        });
        
        it('propagates effect failures', function () {
            $effect = Eff::fail(new \LogicException('Effect failed'));
            $layer = Layer::fromEffect($effect, TestService::class);
            
            expect($layer->build())->toFailWith(\LogicException::class);
        });
    });
    
    describe('combineWith', function () {
        it('combines multiple layers', function () {
            $service1 = new TestServiceImpl('first service');
            $service2 = new AnotherService(456);
            
            $layer1 = Layer::fromValue($service1, TestService::class);
            $layer2 = Layer::fromValue($service2, AnotherService::class);
            
            $combinedLayer = $layer1->combineWith($layer2);
            $context = runEffect($combinedLayer->build());
            
            expect($context->hasService(TestService::class))->toBeTrue()
                ->and($context->hasService(AnotherService::class))->toBeTrue()
                ->and($context->getService(TestService::class))->toBe($service1)
                ->and($context->getService(AnotherService::class))->toBe($service2);
        });
        
        it('fails if any layer fails', function () {
            $goodLayer = Layer::fromValue(new TestServiceImpl('good'), TestService::class);
            $badLayer = Layer::fromEffect(
                Eff::fail(new \RuntimeException('Bad layer')), 
                AnotherService::class
            );
            
            $combined = $goodLayer->combineWith($badLayer);
            
            expect($combined->build())->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('provideTo', function () {
        it('provides layer services to effect', function () {
            $service = new TestServiceImpl('provided service');
            $layer = Layer::fromValue($service, TestService::class);
            
            $effect = Eff::service(TestService::class)
                ->map(fn($svc) => $svc->getValue());
            
            $providedEffect = $layer->provideTo($effect);
            
            expect($providedEffect)->toProduceValue('provided service');
        });
        
        it('enables dependency injection patterns', function () {
            $dbService = new TestServiceImpl('database');
            $logService = new AnotherService(100);
            
            $appLayer = Layer::fromValue($dbService, TestService::class)
                ->combineWith(Layer::fromValue($logService, AnotherService::class));
            
            $businessLogic = Eff::service(TestService::class)
                ->flatMap(fn($db) => Eff::service(AnotherService::class))
                ->map(fn($log) => "Processed with log level: {$log->number}");
            
            $app = $appLayer->provideTo($businessLogic);
            
            expect($app)->toProduceValue('Processed with log level: 100');
        });
    });
    
    describe('complex scenarios', function () {
        it('handles nested layer dependencies', function () {
            // Create a service that depends on another service
            $configLayer = Layer::fromValue(new AnotherService(42), AnotherService::class);
            
            $serviceLayer = Layer::fromEffect(
                Eff::service(AnotherService::class)
                    ->map(fn($config) => new TestServiceImpl("Config: {$config->number}")),
                TestService::class
            );
            
            $appLayer = $configLayer->andThen($serviceLayer);
            
            $effect = Eff::service(TestService::class)
                ->map(fn($svc) => $svc->getValue());
            
            $app = $appLayer->provideTo($effect);
            
            expect($app)->toProduceValue('Config: 42');
        });
    });
});