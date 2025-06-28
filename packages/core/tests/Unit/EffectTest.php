<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Either;
use EffectPHP\Core\Option;
use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Exceptions\UnknownException;

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
    
    describe('try', function () {
        it('executes computation that succeeds', function () {
            $effect = Eff::try(fn() => json_decode('{"key": "value"}', true, 512, JSON_THROW_ON_ERROR));
            
            expect($effect)->toProduceValue(['key' => 'value']);
        });
        
        it('wraps thrown exceptions in UnknownException', function () {
            $effect = Eff::try(fn() => json_decode('invalid json', true, 512, JSON_THROW_ON_ERROR));
            
            expect($effect)->toFailWith(UnknownException::class);
        });
        
        it('preserves original exception as previous', function () {
            $effect = Eff::try(fn() => throw new \RuntimeException('Original error'));
            
            $result = runEffectSafely($effect);
            expect($result->isLeft())->toBeTrue();
            
            $error = $result->fold(fn($e) => $e, fn($v) => null);
            expect($error)->toBeInstanceOf(UnknownException::class);
            expect($error->getPrevious())->toBeInstanceOf(\RuntimeException::class);
            expect($error->getPrevious()->getMessage())->toBe('Original error');
        });
    });
    
    describe('tryWithCatch', function () {
        it('executes computation that succeeds', function () {
            $effect = Eff::tryWithCatch(
                fn() => 42,
                fn(\Throwable $e) => new \LogicException('Custom error')
            );
            
            expect($effect)->toProduceValue(42);
        });
        
        it('applies custom error handler on exception', function () {
            $effect = Eff::tryWithCatch(
                fn() => throw new \RuntimeException('Original'),
                fn(\Throwable $e) => new \LogicException('Custom: ' . $e->getMessage())
            );
            
            $result = runEffectSafely($effect);
            expect($result->isLeft())->toBeTrue();
            
            $error = $result->fold(fn($e) => $e, fn($v) => null);
            expect($error)->toBeInstanceOf(\LogicException::class);
            expect($error->getMessage())->toBe('Custom: Original');
        });
    });
    
    describe('suspend', function () {
        it('creates suspended effect that evaluates lazily', function () {
            $called = false;
            $effect = Eff::suspend(function() use (&$called) {
                $called = true;
                return Eff::succeed(42);
            });
            
            // Effect should not be evaluated until run
            expect($called)->toBeFalse();
            
            expect($effect)->toProduceValue(42);
            expect($called)->toBeTrue();
        });
        
        it('enables stack-safe recursion', function () {
            $countdown = function($n) use (&$countdown) {
                return $n <= 0 
                    ? Eff::succeed($n)
                    : Eff::suspend(fn() => $countdown($n - 1));
            };
            
            $effect = $countdown(1000);
            expect($effect)->toProduceValue(0);
        });
        
        it('preserves errors in suspended computation', function () {
            $effect = Eff::suspend(fn() => Eff::fail(new \RuntimeException('Suspended error')));
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    
    describe('promise', function () {
        it('executes async computation that cannot fail', function () {
            $effect = Eff::promise(fn() => 'promise result');
            
            expect($effect)->toProduceValue('promise result');
        });
        
        it('handles promise-like computations', function () {
            $effect = Eff::promise(fn() => ['data' => 'success']);
            
            expect($effect)->toProduceValue(['data' => 'success']);
        });
    });
    
    describe('tryPromise', function () {
        it('executes async computation that succeeds', function () {
            $effect = Eff::tryPromise(fn() => 'success');
            
            expect($effect)->toProduceValue('success');
        });
        
        it('wraps async exceptions in UnknownException', function () {
            $effect = Eff::tryPromise(fn() => throw new \RuntimeException('Async error'));
            
            expect($effect)->toFailWith(UnknownException::class);
        });
        
        it('preserves original exception as previous', function () {
            $effect = Eff::tryPromise(fn() => throw new \LogicException('Original async error'));
            
            $result = runEffectSafely($effect);
            expect($result->isLeft())->toBeTrue();
            
            $error = $result->fold(fn($e) => $e, fn($v) => null);
            expect($error)->toBeInstanceOf(UnknownException::class);
            expect($error->getPrevious())->toBeInstanceOf(\LogicException::class);
            expect($error->getPrevious()->getMessage())->toBe('Original async error');
        });
    });
    
    describe('tryPromiseWith', function () {
        it('executes async computation that succeeds', function () {
            $effect = Eff::tryPromiseWith(
                fn() => 'async success',
                fn(\Throwable $e) => new \LogicException('Custom async error')
            );
            
            expect($effect)->toProduceValue('async success');
        });
        
        it('applies custom error handler on async exception', function () {
            $effect = Eff::tryPromiseWith(
                fn() => throw new \RuntimeException('Async original'),
                fn(\Throwable $e) => new \LogicException('Custom async: ' . $e->getMessage())
            );
            
            $result = runEffectSafely($effect);
            expect($result->isLeft())->toBeTrue();
            
            $error = $result->fold(fn($e) => $e, fn($v) => null);
            expect($error)->toBeInstanceOf(\LogicException::class);
            expect($error->getMessage())->toBe('Custom async: Async original');
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
    
    describe('raceAll', function () {
        it('returns first successful effect', function () {
            $effects = [
                Eff::succeed('first'),
                Eff::succeed('second'),
                Eff::succeed('third')
            ];
            
            $raced = Eff::raceAll($effects);
            
            expect($raced)->toProduceValue('first');
        });
        
        it('handles single effect', function () {
            $effects = [Eff::succeed('only')];
            
            $raced = Eff::raceAll($effects);
            
            expect($raced)->toProduceValue('only');
        });
    });
    
    describe('sleepFor', function () {
        it('creates sleep effect', function () {
            $duration = Duration::milliseconds(10);
            $effect = Eff::sleepFor($duration);
            
            expect($effect)->toBeEffect();
            expect($effect)->toProduceValue(null);
        });
    });
    
    describe('never', function () {
        it('creates effect that never completes', function () {
            $effect = Eff::never();
            
            expect($effect)->toBeEffect();
        });
    });
    
    describe('service', function () {
        it('creates service access effect', function () {
            $effect = Eff::service('TestService');
            
            expect($effect)->toBeEffect();
        });
    });
    
    describe('clock', function () {
        it('creates clock service access effect', function () {
            $effect = Eff::clock();
            
            expect($effect)->toBeEffect();
        });
    });
    
    describe('currentTimeMillis', function () {
        it('creates current time effect', function () {
            $effect = Eff::currentTimeMillis();
            
            expect($effect)->toBeEffect();
        });
    });
    
    describe('clockWith', function () {
        it('executes effect with clock access', function () {
            $effect = Eff::clockWith(fn($clock) => Eff::succeed('clock result'));
            
            expect($effect)->toBeEffect();
        });
    });
    
    describe('effect chaining and combinators', function () {
        describe('orElse (via method)', function () {
            it('returns primary when successful', function () {
                $primary = Eff::succeed('primary');
                $fallback = Eff::succeed('fallback');
                
                $result = $primary->orElse($fallback);
                
                expect($result)->toProduceValue('primary');
            });
            
            it('returns fallback when primary fails', function () {
                $primary = Eff::fail(new \RuntimeException('Failed'));
                $fallback = Eff::succeed('fallback');
                
                $result = $primary->orElse($fallback);
                
                expect($result)->toProduceValue('fallback');
            });
        });
        
        describe('catchError (via method)', function () {
            it('catches errors and recovers', function () {
                $effect = Eff::fail(new \RuntimeException('Error'))
                    ->catchError(\RuntimeException::class, fn($error) => Eff::succeed('recovered'));
                
                expect($effect)->toProduceValue('recovered');
            });
            
            it('preserves successful values', function () {
                $effect = Eff::succeed('success')
                    ->catchError(\RuntimeException::class, fn($error) => Eff::succeed('recovered'));
                
                expect($effect)->toProduceValue('success');
            });
        });
        
        describe('timeoutAfter (via method)', function () {
            it('completes effect within timeout', function () {
                $duration = Duration::milliseconds(100);
                $effect = Eff::succeed('completed')->timeoutAfter($duration);
                
                expect($effect)->toProduceValue('completed');
            });
        });
        
        describe('retryWith (via method)', function () {
            it('creates retry effect structure', function () {
                $schedule = \EffectPHP\Core\Schedule\Schedule::once();
                $effect = Eff::succeed('test')->retryWith($schedule);
                
                expect($effect)->toBeEffect();
            });
            
            it('creates retry wrapper around effect', function () {
                $schedule = \EffectPHP\Core\Schedule\Schedule::once();
                $original = Eff::succeed('success');
                $retryEffect = $original->retryWith($schedule);
                
                expect($retryEffect)->toBeEffect();
                expect($retryEffect)->not->toBe($original);
            });
        });
        
        describe('ensuring (via method)', function () {
            it('runs cleanup after success', function () {
                $cleanupRan = false;
                
                $effect = Eff::succeed('result')
                    ->ensuring(function() use (&$cleanupRan) {
                        $cleanupRan = true;
                    });
                
                expect($effect)->toProduceValue('result');
                expect($cleanupRan)->toBeTrue();
            });
            
            it('runs cleanup after failure', function () {
                $cleanupRan = false;
                
                $effect = Eff::fail(new \RuntimeException('Error'))
                    ->ensuring(function() use (&$cleanupRan) {
                        $cleanupRan = true;
                    });
                
                expect($effect)->toFailWith(\RuntimeException::class);
                expect($cleanupRan)->toBeTrue();
            });
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