<?php

declare(strict_types=1);

use EffectPHP\Core\Option;

describe('Option', function () {
    
    describe('construction', function () {
        it('creates Some with value', function () {
            $option = Option::some(42);
            
            expect($option)->toBeInstanceOf(Option::class)
                ->and($option->isSome())->toBeTrue()
                ->and($option->isNone())->toBeFalse();
        });
        
        it('creates None without value', function () {
            $option = Option::none();
            
            expect($option)->toBeInstanceOf(Option::class)
                ->and($option->isNone())->toBeTrue()
                ->and($option->isSome())->toBeFalse();
        });
    });
    
    describe('map', function () {
        it('transforms Some value', function () {
            $option = Option::some(5);
            $result = $option->map(fn($x) => $x * 2);
            
            expect($result->isSome())->toBeTrue()
                ->and($result->whenNone(null))->toBe(10);
        });
        
        it('preserves None through transformation', function () {
            $option = Option::none();
            $result = $option->map(fn($x) => $x * 2);
            
            expect($result->isNone())->toBeTrue();
        });
    });
    
    describe('flatMap', function () {
        it('chains Some transformations', function () {
            $option = Option::some(5);
            $result = $option->flatMap(fn($x) => Option::some($x * 2));
            
            expect($result->isSome())->toBeTrue()
                ->and($result->whenNone(null))->toBe(10);
        });
        
        it('flattens nested Options', function () {
            $option = Option::some(5);
            $result = $option->flatMap(fn($x) => Option::none());
            
            expect($result->isNone())->toBeTrue();
        });
        
        it('preserves None through flatMap', function () {
            $option = Option::none();
            $result = $option->flatMap(fn($x) => Option::some($x * 2));
            
            expect($result->isNone())->toBeTrue();
        });
    });
    
    describe('whenNone', function () {
        it('returns value for Some', function () {
            $option = Option::some('hello');
            $result = $option->whenNone('default');
            
            expect($result)->toBe('hello');
        });
        
        it('returns default for None', function () {
            $option = Option::none();
            $result = $option->whenNone('default');
            
            expect($result)->toBe('default');
        });
    });
    
    describe('otherwiseUse', function () {
        it('returns original Some', function () {
            $option = Option::some(42);
            $alternative = Option::some(100);
            $result = $option->otherwiseUse($alternative);
            
            expect($result->whenNone(null))->toBe(42);
        });
        
        it('returns alternative for None', function () {
            $option = Option::none();
            $alternative = Option::some(100);
            $result = $option->otherwiseUse($alternative);
            
            expect($result->whenNone(null))->toBe(100);
        });
    });
    
    describe('toEffect', function () {
        it('converts Some to successful Effect', function () {
            $option = Option::some(42);
            $effect = $option->toEffect(new \Exception('Empty'));
            
            expect($effect)->toProduceValue(42);
        });
        
        it('converts None to failed Effect', function () {
            $option = Option::none();
            $effect = $option->toEffect(new \RuntimeException('Empty'));
            
            expect($effect)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('functor laws', function () {
        it('preserves identity', function () {
            $option = Option::some(42);
            $result = $option->map(fn($x) => $x);
            
            expect($result->whenNone(null))->toBe(42);
        });
        
        it('preserves composition', function () {
            $option = Option::some(5);
            $f = fn($x) => $x * 2;
            $g = fn($x) => $x + 1;
            
            $composed = $option->map($f)->map($g);
            $direct = $option->map(fn($x) => $g($f($x)));
            
            expect($composed->whenNone(null))->toBe($direct->whenNone(null));
        });
    });
    
    describe('monad laws', function () {
        it('left identity', function () {
            $value = 42;
            $f = fn($x) => Option::some($x * 2);
            
            $leftSide = Option::some($value)->flatMap($f);
            $rightSide = $f($value);
            
            expect($leftSide->whenNone(null))->toBe($rightSide->whenNone(null));
        });
        
        it('right identity', function () {
            $option = Option::some(42);
            $result = $option->flatMap(fn($x) => Option::some($x));
            
            expect($result->whenNone(null))->toBe($option->whenNone(null));
        });
        
        it('associativity', function () {
            $option = Option::some(5);
            $f = fn($x) => Option::some($x * 2);
            $g = fn($x) => Option::some($x + 1);
            
            $leftAssoc = $option->flatMap($f)->flatMap($g);
            $rightAssoc = $option->flatMap(fn($x) => $f($x)->flatMap($g));
            
            expect($leftAssoc->whenNone(null))->toBe($rightAssoc->whenNone(null));
        });
    });
});