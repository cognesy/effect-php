<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\RuntimeManager;

describe('Runtime', function () {
    
    describe('default runtime', function () {
        it('provides singleton runtime instance', function () {
            $runtime1 = RuntimeManager::default();
            $runtime2 = RuntimeManager::default();
            
            expect($runtime1)->toBe($runtime2);
        });
    });
    
    describe('createWith', function () {
        it('creates runtime with custom context', function () {
            $context = Context::empty()->withService('test', new stdClass());
            $runtime = RuntimeManager::createWith($context);
            
            expect($runtime)->toBeInstanceOf(Runtime::class);
        });
    });
    
    describe('runSafely', function () {
        it('returns Right for successful effects', function () {
            $effect = Eff::succeed(42);
            
            $result = Eff::runSafely($effect);
            
            expect($result)->toBeEither()
                ->and($result->isRight())->toBeTrue()
                ->and($result->fold(fn($l) => null, fn($r) => $r))->toBe(42);
        });
        
        it('returns Left for failed effects', function () {
            $effect = Eff::fail(new \RuntimeException('Test error'));
            
            $result = Eff::runSafely($effect);
            
            expect($result)->toBeEither()
                ->and($result->isLeft())->toBeTrue();
            
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error)->toBeInstanceOf(\RuntimeException::class)
                ->and($error->getMessage())->toBe('Test error');
        });
        
        it('handles synchronous computations safely', function () {
            $effect = Eff::sync(fn() => 21 * 2);
            
            $result = Eff::runSafely($effect);
            
            expect($result->isRight())->toBeTrue()
                ->and($result->fold(fn($l) => null, fn($r) => $r))->toBe(42);
        });
        
        it('catches exceptions in synchronous computations', function () {
            $effect = Eff::sync(fn() => throw new \LogicException('Sync error'));
            
            $result = Eff::runSafely($effect);
            
            expect($result->isLeft())->toBeTrue();
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error)->toBeInstanceOf(\LogicException::class);
        });
    });
    
    describe('unsafeRun', function () {
        it('executes successful effects', function () {
            $effect = Eff::succeed('hello world');
            
            $result = Eff::runSync($effect);
            
            expect($result)->toBe('hello world');
        });
        
        it('throws exceptions for failed effects', function () {
            $effect = Eff::fail(new \RuntimeException('Test error'));
            
            expect(fn() => Eff::runSync($effect))
                ->toThrow(\RuntimeException::class, 'Test error');
        });
        
        it('executes complex effect chains', function () {
            $effect = Eff::succeed(5)
                ->map(fn($x) => $x * 2)
                ->flatMap(fn($x) => Eff::succeed($x + 10))
                ->map(fn($x) => "Result: $x");
            
            $result = Eff::runSync($effect);
            
            expect($result)->toBe('Result: 20');
        });
        
        it('handles nested flatMap operations', function () {
            $effect = Eff::succeed(1)
                ->flatMap(fn($x) => Eff::succeed($x + 1))
                ->flatMap(fn($x) => Eff::succeed($x * 3))
                ->flatMap(fn($x) => Eff::succeed($x - 1));
            
            $result = Eff::runSync($effect);
            
            expect($result)->toBe(5); // ((1 + 1) * 3) - 1 = 5
        });
    });
    
    describe('stack safety', function () {
        it('handles deep effect chains without stack overflow', function () {
            $effect = Eff::succeed(0);
            for ($i = 0; $i < 10000; $i++) {
                $effect = $effect->flatMap(fn($x) => Eff::succeed($x + 1));
            }
            
            $result = Eff::runSync($effect);
            
            expect($result)->toBe(10000);
        });
        
        it('handles deep map chains without stack overflow', function () {
            $effect = Eff::succeed(0);
            for ($i = 0; $i < 10000; $i++) {
                $effect = $effect->map(fn($x) => $x + 1);
            }
            
            $result = Eff::runSync($effect);
            
            expect($result)->toBe(10000);
        });
        
        it('handles recursive effects safely', function () {
            $countdown = function($n) use (&$countdown) {
                return $n <= 0 
                    ? Eff::succeed('done')
                    : Eff::succeed($n)->flatMap(fn($x) => $countdown($x - 1));
            };
            
            $result = Eff::runSync($countdown(1000));
            
            expect($result)->toBe('done');
        });
    });
    
    describe('service access', function () {
        it('provides services from context', function () {
            $service = new stdClass();
            $service->value = 42;
            
            $context = Context::empty()->withService(stdClass::class, $service);
            $runtime = RuntimeManager::createWith($context);
            
            $effect = Eff::service(stdClass::class);
            $result = $runtime->unsafeRun($effect);
            
            expect($result)->toBe($service)
                ->and($result->value)->toBe(42);
        });
        
        it('throws exception for missing services', function () {
            $effect = Eff::service('NonExistentService');
            
            expect(fn() => Eff::runSync($effect))
                ->toThrow(ServiceNotFoundException::class);
        });
    });
    
    describe('parallel execution', function () {
        it('executes multiple effects in parallel', function () {
            $effects = [
                Eff::succeed(1),
                Eff::succeed(2),
                Eff::succeed(3)
            ];
            
            $parallel = Eff::allInParallel($effects);
            $result = Eff::runSync($parallel);
            
            expect($result)->toBe([1, 2, 3]);
        });
        
        it('fails fast on first error in parallel execution', function () {
            $effects = [
                Eff::succeed(1),
                Eff::fail(new \RuntimeException('Parallel error')),
                Eff::succeed(3)
            ];
            
            $parallel = Eff::allInParallel($effects);
            
            expect(fn() => Eff::runSync($parallel))
                ->toThrow(\RuntimeException::class, 'Parallel error');
        });
    });
    
    describe('error propagation', function () {
        it('propagates errors through effect chains', function () {
            $effect = Eff::succeed(5)
                ->flatMap(fn($x) => Eff::fail(new \LogicException('Chain error')))
                ->map(fn($x) => $x * 2); // This should not execute
            
            expect(fn() => Eff::runSync($effect))
                ->toThrow(\LogicException::class, 'Chain error');
        });
        
        it('preserves original error information', function () {
            $originalError = new \RuntimeException('Original error', 123);
            
            $effect = Eff::fail($originalError)
                ->map(fn($x) => $x * 2)
                ->flatMap(fn($x) => Eff::succeed($x + 1));
            
            try {
                Eff::runSync($effect);
                expect(false)->toBeTrue('Should have thrown exception');
            } catch (\RuntimeException $e) {
                expect($e)->toBe($originalError)
                    ->and($e->getCode())->toBe(123);
            }
        });
    });
    
    describe('context propagation', function () {
        it('provides context through effect chains', function () {
            $service = new stdClass();
            $service->name = 'test service';
            
            $context = Context::empty()->withService(stdClass::class, $service);
            $runtime = RuntimeManager::createWith($context);
            
            $effect = Eff::service(stdClass::class)
                ->flatMap(fn($svc) => Eff::succeed($svc->name))
                ->map(fn($name) => strtoupper($name));
            
            $result = $runtime->unsafeRun($effect);
            
            expect($result)->toBe('TEST SERVICE');
        });
    });
});