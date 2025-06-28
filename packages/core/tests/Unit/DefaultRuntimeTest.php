<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Result\Result;
use EffectPHP\Core\Runtime\DefaultRuntime;

describe('DefaultRuntime', function () {
    
    it('has correct name', function () {
        $runtime = new DefaultRuntime();
        expect($runtime->getName())->toBe('DefaultRuntime');
    });
    
    it('executes successful effects', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::succeed(42);
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe(42);
    });
    
    it('throws exceptions for failed effects', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::fail(new \RuntimeException('Test error'));
        
        expect(fn() => $runtime->unsafeRun($effect))
            ->toThrow(\RuntimeException::class, 'Test error');
    });
    
    it('returns Result for runSafely with success', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::succeed(100);
        
        $result = $runtime->runSafely($effect);
        
        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->isSuccess())->toBeTrue()
            ->and($result->fold(fn($cause) => null, fn($r) => $r))->toBe(100);
    });
    
    it('returns Result for runSafely with failure', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::fail(new \LogicException('Logic error'));
        
        $result = $runtime->runSafely($effect);
        
        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->isFailure())->toBeTrue();
        
        $error = $result->fold(fn($cause) => $cause->error, fn($r) => null);
        expect($error)->toBeInstanceOf(\LogicException::class)
            ->and($error->getMessage())->toBe('Logic error');
    });
    
    it('handles effect chains', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::succeed(10)
            ->map(fn($x) => $x * 2)
            ->flatMap(fn($x) => Eff::succeed($x + 5))
            ->map(fn($x) => "Result: $x");
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe('Result: 25');
    });
    
    it('handles deep flatMap chains without stack overflow', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::succeed(0);
        
        for ($i = 0; $i < 1000; $i++) {
            $effect = $effect->flatMap(fn($x) => Eff::succeed($x + 1));
        }
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe(1000);
    });
    
    it('handles deep map chains without stack overflow', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::succeed(0);
        
        for ($i = 0; $i < 1000; $i++) {
            $effect = $effect->map(fn($x) => $x + 1);
        }
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe(1000);
    });
    
    it('propagates errors through effect chains', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::succeed(5)
            ->flatMap(fn($x) => Eff::fail(new \InvalidArgumentException('Chain error')))
            ->map(fn($x) => $x * 2);
        
        expect(fn() => $runtime->unsafeRun($effect))
            ->toThrow(\InvalidArgumentException::class, 'Chain error');
    });
    
    it('creates runtime with custom context', function () {
        $service = new stdClass();
        $service->value = 'test service';
        
        $context = Context::empty()->withService(stdClass::class, $service);
        $runtime = new DefaultRuntime($context);
        
        $effect = Eff::service(stdClass::class);
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe($service)
            ->and($result->value)->toBe('test service');
    });
    
    it('creates runtime using createWith factory', function () {
        $service = new stdClass();
        $service->name = 'factory service';
        
        $context = Context::empty()->withService(stdClass::class, $service);
        $runtime = DefaultRuntime::createWith($context);
        
        $effect = Eff::service(stdClass::class)
            ->map(fn($svc) => $svc->name);
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe('factory service');
    });
    
    it('provides context through effect chains', function () {
        $service = new stdClass();
        $service->data = 'context data';
        
        $context = Context::empty()->withService(stdClass::class, $service);
        $runtime = new DefaultRuntime($context);
        
        $effect = Eff::service(stdClass::class)
            ->flatMap(fn($svc) => Eff::succeed($svc->data))
            ->map(fn($data) => strtoupper($data));
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe('CONTEXT DATA');
    });
    
    it('executes parallel effects', function () {
        $runtime = new DefaultRuntime();
        $effects = [
            Eff::succeed(1),
            Eff::succeed(2),
            Eff::succeed(3)
        ];
        
        $parallel = Eff::allInParallel($effects);
        $result = $runtime->unsafeRun($parallel);
        
        expect($result)->toBe([1, 2, 3]);
    });
    
    it('fails fast on parallel execution error', function () {
        $runtime = new DefaultRuntime();
        $effects = [
            Eff::succeed(1),
            Eff::fail(new \DomainException('Parallel error')),
            Eff::succeed(3)
        ];
        
        $parallel = Eff::allInParallel($effects);
        
        expect(fn() => $runtime->unsafeRun($parallel))
            ->toThrow(\DomainException::class, 'Parallel error');
    });
    
    it('handles sync effects with exceptions', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::sync(fn() => throw new \RuntimeException('Sync exception'));
        
        expect(fn() => $runtime->unsafeRun($effect))
            ->toThrow(\RuntimeException::class, 'Sync exception');
    });
    
    it('handles sync effects with return values', function () {
        $runtime = new DefaultRuntime();
        $effect = Eff::sync(fn() => 42 * 2);
        
        $result = $runtime->unsafeRun($effect);
        
        expect($result)->toBe(84);
    });
});