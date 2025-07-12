<?php declare(strict_types=1);

use EffectPHP\Utils\Duration;

describe('Duration', function () {
    
    describe('construction', function () {
        it('creates duration from seconds', function () {
            $duration = Duration::seconds(5);
            
            expect($duration->toSeconds())->toBe(5)
                ->and($duration->toMilliseconds())->toBe(5000)
                ->and($duration->toMicroseconds())->toBe(5_000_000);
        });
        
        it('creates duration from milliseconds', function () {
            $duration = Duration::milliseconds(1500);
            
            expect($duration->toSeconds())->toBe(1)
                ->and($duration->toMilliseconds())->toBe(1500)
                ->and($duration->toMicroseconds())->toBe(1_500_000);
        });
        
        it('creates duration from microseconds', function () {
            $duration = Duration::microseconds(2_500_000);
            
            expect($duration->toSeconds())->toBe(2)
                ->and($duration->toMilliseconds())->toBe(2500)
                ->and($duration->toMicroseconds())->toBe(2_500_000);
        });
        
        it('creates duration from minutes', function () {
            $duration = Duration::minutes(2);
            
            expect($duration->toSeconds())->toBe(120);
        });
        
        it('creates duration from hours', function () {
            $duration = Duration::hours(1);
            
            expect($duration->toSeconds())->toBe(3600);
        });
    });
    
    describe('arithmetic operations', function () {
        it('adds durations correctly', function () {
            $duration1 = Duration::seconds(3);
            $duration2 = Duration::milliseconds(500);
            
            $sum = $duration1->plus($duration2);
            
            expect($sum->toMilliseconds())->toBe(3500);
        });
        
        it('handles nanosecond overflow in addition', function () {
            $duration1 = Duration::milliseconds(999);
            $duration2 = Duration::milliseconds(2);
            
            $sum = $duration1->plus($duration2);
            
            expect($sum->toSeconds())->toBe(1)
                ->and($sum->toMilliseconds())->toBe(1001);
        });
        
        it('multiplies duration by factor', function () {
            $duration = Duration::seconds(2);
            $multiplied = $duration->times(2.5);
            
            expect($multiplied->toSeconds())->toBe(5);
        });
        
        it('handles fractional multiplication', function () {
            $duration = Duration::milliseconds(1000);
            $multiplied = $duration->times(0.5);
            
            expect($multiplied->toMilliseconds())->toBe(500);
        });
    });
    
    describe('precision handling', function () {
        it('maintains precision with nanoseconds', function () {
            $duration = Duration::microseconds(1001); // 1.001 ms
            
            expect($duration->toMicroseconds())->toBe(1001);
        });
        
        it('handles large durations', function () {
            $duration = Duration::hours(24);
            
            expect($duration->toSeconds())->toBe(86400) // 24 * 60 * 60
                ->and($duration->toMilliseconds())->toBe(86_400_000);
        });
    });
    
    describe('edge cases', function () {
        it('handles zero duration', function () {
            $duration = Duration::seconds(0);
            
            expect($duration->toSeconds())->toBe(0)
                ->and($duration->toMilliseconds())->toBe(0)
                ->and($duration->toMicroseconds())->toBe(0);
        });
        
        it('handles very small durations', function () {
            $duration = Duration::microseconds(1);
            
            expect($duration->toMicroseconds())->toBe(1)
                ->and($duration->toMilliseconds())->toBe(0) // Rounded down
                ->and($duration->toSeconds())->toBe(0);
        });
    });
});
