<?php

declare(strict_types=1);

use EffectPHP\Core\Either;

describe('Either', function () {
    
    describe('construction', function () {
        it('creates Left with error value', function () {
            $either = Either::left('error');
            
            expect($either)->toBeInstanceOf(Either::class)
                ->and($either->isLeft())->toBeTrue()
                ->and($either->isRight())->toBeFalse();
        });
        
        it('creates Right with success value', function () {
            $either = Either::right(42);
            
            expect($either)->toBeInstanceOf(Either::class)
                ->and($either->isRight())->toBeTrue()
                ->and($either->isLeft())->toBeFalse();
        });
    });
    
    describe('map', function () {
        it('transforms Right value', function () {
            $either = Either::right(5);
            $result = $either->map(fn($x) => $x * 2);
            
            $value = $result->fold(fn($cause) => null, fn($r) => $r);
            expect($result->isRight())->toBeTrue()
                ->and($value)->toBe(10);
        });
        
        it('preserves Left through transformation', function () {
            $either = Either::left('error');
            $result = $either->map(fn($x) => $x * 2);
            
            expect($result->isLeft())->toBeTrue()
                ->and($result->getLeftOrNull())->toBe('error');
        });
    });
    
    describe('mapLeft', function () {
        it('transforms Left value', function () {
            $either = Either::left('error');
            $result = $either->mapLeft(fn($e) => strtoupper($e));
            
            expect($result->isLeft())->toBeTrue()
                ->and($result->getLeftOrNull())->toBe('ERROR');
        });
        
        it('preserves Right through left transformation', function () {
            $either = Either::right(42);
            $result = $either->mapLeft(fn($e) => strtoupper($e));
            
            expect($result->isRight())->toBeTrue()
                ->and($result->getRightOrNull())->toBe(42);
        });
    });
    
    describe('flatMap', function () {
        it('chains Right transformations', function () {
            $either = Either::right(5);
            $result = $either->flatMap(fn($x) => Either::right($x * 2));
            
            expect($result->isRight())->toBeTrue()
                ->and($result->getRightOrNull())->toBe(10);
        });
        
        it('short-circuits on Left', function () {
            $either = Either::left('error');
            $result = $either->flatMap(fn($x) => Either::right($x * 2));
            
            expect($result->isLeft())->toBeTrue()
                ->and($result->getLeftOrNull())->toBe('error');
        });
        
        it('propagates inner Left', function () {
            $either = Either::right(5);
            $result = $either->flatMap(fn($x) => Either::left('inner error'));
            
            expect($result->isLeft())->toBeTrue()
                ->and($result->getLeftOrNull())->toBe('inner error');
        });
    });
    
    describe('fold', function () {
        it('applies left function to Left', function () {
            $either = Either::left('error');
            $result = $either->fold(
                fn($l) => "Error: $l",
                fn($r) => "Success: $r"
            );
            
            expect($result)->toBe('Error: error');
        });
        
        it('applies right function to Right', function () {
            $either = Either::right(42);
            $result = $either->fold(
                fn($l) => "Error: $l",
                fn($r) => "Success: $r"
            );
            
            expect($result)->toBe('Success: 42');
        });
    });
    
    describe('toEffect', function () {
        it('converts Right to successful Effect', function () {
            $either = Either::right(42);
            $effect = $either->toEffect();
            
            expect($effect)->toProduceValue(42);
        });
        
        it('converts Left with Throwable to failed Effect', function () {
            $exception = new \RuntimeException('test error');
            $either = Either::left($exception);
            $effect = $either->toEffect();
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
        
        it('converts Left with string to failed Effect', function () {
            $either = Either::left('string error');
            $effect = $either->toEffect();
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('functor laws for Right', function () {
        it('preserves identity', function () {
            $either = Either::right(42);
            $result = $either->map(fn($x) => $x);
            
            expect($result->fold(fn($cause) => null, fn($r) => $r))->toBe(42);
        });
        
        it('preserves composition', function () {
            $either = Either::right(5);
            $f = fn($x) => $x * 2;
            $g = fn($x) => $x + 1;
            
            $composed = $either->map($f)->map($g);
            $direct = $either->map(fn($x) => $g($f($x)));
            
            expect($composed->fold(fn($cause) => null, fn($r) => $r))
                ->toBe($direct->fold(fn($cause) => null, fn($r) => $r));
        });
    });
    
    describe('monad laws for Right', function () {
        it('left identity', function () {
            $value = 42;
            $f = fn($x) => Either::right($x * 2);
            
            $leftSide = Either::right($value)->flatMap($f);
            $rightSide = $f($value);
            
            expect($leftSide->fold(fn($cause) => null, fn($r) => $r))
                ->toBe($rightSide->fold(fn($cause) => null, fn($r) => $r));
        });
        
        it('right identity', function () {
            $either = Either::right(42);
            $result = $either->flatMap(fn($x) => Either::right($x));
            
            expect($result->fold(fn($cause) => null, fn($r) => $r))
                ->toBe($either->fold(fn($cause) => null, fn($r) => $r));
        });
        
        it('associativity', function () {
            $either = Either::right(5);
            $f = fn($x) => Either::right($x * 2);
            $g = fn($x) => Either::right($x + 1);
            
            $leftAssoc = $either->flatMap($f)->flatMap($g);
            $rightAssoc = $either->flatMap(fn($x) => $f($x)->flatMap($g));
            
            expect($leftAssoc->fold(fn($cause) => null, fn($r) => $r))
                ->toBe($rightAssoc->fold(fn($cause) => null, fn($r) => $r));
        });
    });
    
    describe('convenience methods', function () {
        it('provides safe null-returning accessors', function () {
            $left = Either::left('error');
            $right = Either::right(42);
            
            expect($left->getLeftOrNull())->toBe('error')
                ->and($left->getRightOrNull())->toBeNull()
                ->and($right->getLeftOrNull())->toBeNull()
                ->and($right->getRightOrNull())->toBe(42);
        });
        
        it('provides direct accessors that throw on wrong type', function () {
            $left = Either::left('error');
            $right = Either::right(42);
            
            expect($left->getLeft())->toBe('error')
                ->and($right->getRight())->toBe(42);
                
            expect(fn() => $left->getRight())
                ->toThrow(\RuntimeException::class, 'Cannot get right value from Left Either');
                
            expect(fn() => $right->getLeft())
                ->toThrow(\RuntimeException::class, 'Cannot get left value from Right Either');
        });
    });
    
    describe('error handling patterns', function () {
        it('chains multiple operations that can fail', function () {
            $parseNumber = fn($str) => is_numeric($str) 
                ? Either::right((int)$str) 
                : Either::left("Not a number: $str");
                
            $double = fn($x) => Either::right($x * 2);
            
            $result1 = $parseNumber('42')->flatMap($double);
            $result2 = $parseNumber('abc')->flatMap($double);
            
            expect($result1->isRight())->toBeTrue()
                ->and($result1->getRightOrNull())->toBe(84)
                ->and($result2->isLeft())->toBeTrue()
                ->and($result2->getLeftOrNull())->toBe('Not a number: abc');
        });
    });
});