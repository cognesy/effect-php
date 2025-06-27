<?php

declare(strict_types=1);

use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Schedule\Schedule;
use EffectPHP\Core\Schedule\Nodes\ScheduleNode;

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

describe('Schedule', function () {
    
    describe('basic schedules', function () {
        it('creates once schedule', function () {
            $schedule = Schedule::once();
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates fixed delay schedule', function () {
            $delay = Duration::milliseconds(100);
            $schedule = Schedule::fixedDelay($delay);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates exponential backoff schedule', function () {
            $base = Duration::milliseconds(50);
            $schedule = Schedule::exponentialBackoff($base);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates exponential backoff with custom factor', function () {
            $base = Duration::milliseconds(50);
            $schedule = Schedule::exponentialBackoff($base, 3.0);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates fibonacci backoff schedule', function () {
            $base = Duration::milliseconds(10);
            $schedule = Schedule::fibonacciBackoff($base);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates linear backoff schedule', function () {
            $base = Duration::milliseconds(25);
            $schedule = Schedule::linearBackoff($base);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
    });
    
    describe('schedule modifiers', function () {
        it('limits retries with upToMaxRetries', function () {
            $schedule = Schedule::fixedDelay(Duration::milliseconds(100))
                ->upToMaxRetries(5);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('limits duration with upToMaxDuration', function () {
            $schedule = Schedule::exponentialBackoff(Duration::milliseconds(10))
                ->upToMaxDuration(Duration::seconds(30));
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('adds jitter to schedule', function () {
            $schedule = Schedule::fixedDelay(Duration::milliseconds(100))
                ->withJitter();
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('adds custom jitter factor', function () {
            $schedule = Schedule::exponentialBackoff(Duration::milliseconds(50))
                ->withJitter(0.2);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
    });
    
    describe('schedule composition', function () {
        it('chains multiple modifiers', function () {
            $schedule = Schedule::exponentialBackoff(Duration::milliseconds(100))
                ->upToMaxRetries(10)
                ->upToMaxDuration(Duration::minutes(5))
                ->withJitter(0.1);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates complex retry policies', function () {
            $schedule = Schedule::fibonacciBackoff(Duration::milliseconds(25))
                ->upToMaxRetries(20)
                ->withJitter(0.15);
            
            expect($schedule)->toBeInstanceOf(Schedule::class);
        });
    });
    
    describe('realistic retry scenarios', function () {
        it('creates database retry schedule', function () {
            $dbRetrySchedule = Schedule::exponentialBackoff(Duration::milliseconds(100), 2.0)
                ->upToMaxRetries(5)
                ->upToMaxDuration(Duration::seconds(30))
                ->withJitter(0.1);
            
            expect($dbRetrySchedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates network retry schedule', function () {
            $networkRetrySchedule = Schedule::linearBackoff(Duration::milliseconds(200))
                ->upToMaxRetries(3)
                ->withJitter(0.2);
            
            expect($networkRetrySchedule)->toBeInstanceOf(Schedule::class);
        });
        
        it('creates file system retry schedule', function () {
            $fsRetrySchedule = Schedule::fixedDelay(Duration::milliseconds(50))
                ->upToMaxRetries(10)
                ->upToMaxDuration(Duration::seconds(5));
            
            expect($fsRetrySchedule)->toBeInstanceOf(Schedule::class);
        });
    });
    
    describe('schedule node access', function () {
        it('provides access to internal node structure', function () {
            $schedule = Schedule::exponentialBackoff(Duration::milliseconds(100));
            $node = $schedule->getNode();
            
            expect($node)->toBeInstanceOf(ScheduleNode::class);
        });
    });
});