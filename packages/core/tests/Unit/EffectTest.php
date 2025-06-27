<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Either;
use EffectPHP\Core\Option;

describe('Effect', function () {
    
    describe('succeed', function () {
        it('creates successful effect', function () {
            $effect = Eff::succeed(42);
            
            expect($effect)->toBeEffect()
                ->and($effect)->toProduceValue(42);
        });
        
        it('handles null values', function () {
            $effect = Eff::succeed(null);
            
            expect($effect)->toProduceValue(null);
        });
    });
    
    describe('fail', function () {
        it('creates failed effect', function () {
            $error = new \RuntimeException('Test error');
            $effect = Eff::fail($error);
            
            expect($effect)->toBeEffect()
                ->and($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('sync', function () {
        it('executes synchronous computation', function () {
            $effect = Eff::sync(fn() => 21 * 2);
            
            expect($effect)->toProduceValue(42);
        });
        
        it('catches synchronous exceptions', function () {
            $effect = Eff::sync(fn() => throw new \RuntimeException('Sync error'));
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('async', function () {
        it('executes asynchronous computation', function () {
            $effect = Eff::async(fn() => 'async result');
            
            expect($effect)->toProduceValue('async result');
        });
    });
    
    describe('map', function () {
        it('transforms successful values', function () {
            $effect = Eff::succeed(5)->map(fn($x) => $x * 2);
            
            expect($effect)->toProduceValue(10);
        });
        
        it('preserves failures', function () {
            $effect = Eff::fail(new \RuntimeException('Error'))
                ->map(fn($x) => $x * 2);
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
        
        it('chains multiple transformations', function () {
            $effect = Eff::succeed(5)
                ->map(fn($x) => $x * 2)
                ->map(fn($x) => $x + 1)
                ->map(fn($x) => "Result: $x");
            
            expect($effect)->toProduceValue('Result: 11');
        });
    });
    
    describe('flatMap', function () {
        it('chains dependent computations', function () {
            $effect = Eff::succeed(5)
                ->flatMap(fn($x) => Eff::succeed($x * 2));
            
            expect($effect)->toProduceValue(10);
        });
        
        it('short-circuits on failure', function () {
            $effect = Eff::fail(new \RuntimeException('Error'))
                ->flatMap(fn($x) => Eff::succeed($x * 2));
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
        
        it('propagates inner failures', function () {
            $effect = Eff::succeed(5)
                ->flatMap(fn($x) => Eff::fail(new \LogicException('Inner error')));
            
            expect($effect)->toFailWith(\LogicException::class);
        });
        
        it('enables complex computations', function () {
            $divide = fn($a, $b) => $b === 0
                ? Eff::fail(new \DivisionByZeroError('Division by zero'))
                : Eff::succeed($a / $b);
            
            $computation = Eff::succeed(10)
                ->flatMap(fn($x) => $divide($x, 2))
                ->flatMap(fn($x) => $divide($x, 5));
            
            expect($computation)->toProduceValue(1);
            
            $failingComputation = Eff::succeed(10)
                ->flatMap(fn($x) => $divide($x, 0));
            
            expect($failingComputation)->toFailWith(\DivisionByZeroError::class);
        });
    });
    
    describe('when', function () {
        it('executes effect when condition is true', function () {
            $effect = Eff::when(true, Eff::succeed(42));
            
            expect($effect)->toProduceValue(42);
        });
        
        it('returns null when condition is false', function () {
            $effect = Eff::when(false, Eff::succeed(42));
            
            expect($effect)->toProduceValue(null);
        });
    });
    
    describe('fromOption', function () {
        it('converts Some to successful effect', function () {
            $option = Option::some(42);
            $effect = Eff::fromOption($option, new \RuntimeException('Empty'));
            
            expect($effect)->toProduceValue(42);
        });
        
        it('converts None to failed effect', function () {
            $option = Option::none();
            $effect = Eff::fromOption($option, new \RuntimeException('Empty'));
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('fromEither', function () {
        it('converts Right to successful effect', function () {
            $either = Either::right(42);
            $effect = Eff::fromEither($either);
            
            expect($effect)->toProduceValue(42);
        });
        
        it('converts Left to failed effect', function () {
            $either = Either::left(new \RuntimeException('Error'));
            $effect = Eff::fromEither($either);
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('allInParallel', function () {
        it('combines multiple successful effects', function () {
            $effects = [
                Eff::succeed(1),
                Eff::succeed(2),
                Eff::succeed(3)
            ];
            
            $combined = Eff::allInParallel($effects);
            
            expect($combined)->toProduceValue([1, 2, 3]);
        });
        
        it('fails if any effect fails', function () {
            $effects = [
                Eff::succeed(1),
                Eff::fail(new \RuntimeException('Error')),
                Eff::succeed(3)
            ];
            
            $combined = Eff::allInParallel($effects);
            
            expect($combined)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('monad laws', function () {
        it('left identity', function () {
            $value = 42;
            $f = fn($x) => Eff::succeed($x * 2);
            
            $leftSide = Eff::succeed($value)->flatMap($f);
            $rightSide = $f($value);
            
            $runtime = runtime();
            expect($runtime->unsafeRun($leftSide))
                ->toBe($runtime->unsafeRun($rightSide));
        });
        
        it('right identity', function () {
            $effect = Eff::succeed(42);
            $result = $effect->flatMap(fn($x) => Eff::succeed($x));
            
            $runtime = runtime();
            expect($runtime->unsafeRun($result))
                ->toBe($runtime->unsafeRun($effect));
        });
        
        it('associativity', function () {
            $effect = Eff::succeed(5);
            $f = fn($x) => Eff::succeed($x * 2);
            $g = fn($x) => Eff::succeed($x + 1);
            
            $leftAssoc = $effect->flatMap($f)->flatMap($g);
            $rightAssoc = $effect->flatMap(fn($x) => $f($x)->flatMap($g));
            
            $runtime = runtime();
            expect($runtime->unsafeRun($leftAssoc))
                ->toBe($runtime->unsafeRun($rightAssoc));
        });
    });
    
    describe('stack safety', function () {
        it('handles deep recursion without stack overflow', function () {
            $loop = function($n) use (&$loop) {
                return $n <= 0 
                    ? Eff::succeed($n)
                    : Eff::succeed($n)->flatMap(fn($x) => $loop($x - 1));
            };
            
            $effect = $loop(1000);
            
            expect($effect)->toProduceValue(0);
        });
        
        it('handles long map chains', function () {
            $effect = Eff::succeed(0);
            
            for ($i = 0; $i < 1000; $i++) {
                $effect = $effect->map(fn($x) => $x + 1);
            }
            
            expect($effect)->toProduceValue(1000);
        });
    });
    
    describe('error handling', function () {
        it('preserves error information through chains', function () {
            $originalError = new \RuntimeException('Original error');
            
            $effect = Eff::fail($originalError)
                ->map(fn($x) => $x * 2)
                ->flatMap(fn($x) => Eff::succeed($x + 1));
            
            $result = runEffectSafely($effect);
            
            expect($result->isLeft())->toBeTrue();
            $error = $result->fold(fn($e) => $e, fn($v) => null);
            expect($error)->toBeInstanceOf(\RuntimeException::class)
                ->and($error->getMessage())->toBe('Original error');
        });
    });
});