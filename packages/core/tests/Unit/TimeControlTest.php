<?php

declare(strict_types=1);

use EffectPHP\Core\Clock\SystemClock;
use EffectPHP\Core\Clock\TestClock;
use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Utils\Duration;

describe('Time Control with Effect System', function () {
    
    describe('SystemClock Integration', function () {
        it('provides real system time through Effects', function () {
            $effect = Eff::currentTimeMillis();
            
            $time1 = Run::sync($effect);
            usleep(1000); // 1ms
            $time2 = Run::sync($effect);
            
            expect($time2)->toBeGreaterThan($time1);
        });

        it('performs actual sleep through Effects', function () {
            $start = microtime(true);
            
            $effect = Eff::sleepFor(Duration::milliseconds(10));
            Run::sync($effect);
            
            $elapsed = microtime(true) - $start;
            expect($elapsed)->toBeGreaterThan(0.005); // At least 5ms
        });

        it('provides SystemClock by default in runtime', function () {
            $program = Eff::clock()->map(fn(Clock $clock) => get_class($clock));
            
            expect($program)->toProduceValue(SystemClock::class);
        });
    });

    describe('TestClock Integration', function () {
        it('starts with zero time by default', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $program = $layer->provideTo(Eff::currentTimeMillis());
            
            expect($program)->toProduceValue(0);
        });

        it('starts with specified initial time', function () {
            $testClock = new TestClock(1000);
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $program = $layer->provideTo(Eff::currentTimeMillis());
            
            expect($program)->toProduceValue(1000);
        });

        it('advances time when manually adjusted', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            // Advance time manually (this is TestClock-specific for testing)
            $testClock->adjust(Duration::seconds(5));
            
            $program = $layer->provideTo(Eff::currentTimeMillis());
            
            expect($program)->toProduceValue(5000);
        });

        it('allows absolute time setting for test scenarios', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            // Set absolute time (this is TestClock-specific for testing)
            $testClock->setTime(10000);
            
            $program = $layer->provideTo(Eff::currentTimeMillis());
            
            expect($program)->toProduceValue(10000);
        });

        it('prevents time going backwards in test clock', function () {
            $testClock = new TestClock(1000);
            
            expect(fn() => $testClock->setTime(500))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('enables instant completion of sleep effects', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $start = microtime(true);
            
            // This would take 10 seconds with real clock, but completes instantly with TestClock
            $program = $layer->provideTo(
                Eff::sleepFor(Duration::seconds(10))->map(fn() => 'completed')
            );
            
            $elapsed = microtime(true) - $start;
            
            expect($program)->toProduceValue('completed');
            expect($elapsed)->toBeLessThan(0.1); // Completes almost instantly
        });
    });

    describe('Clock Service Integration', function () {
        it('allows Clock service override through Layer', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $program = $layer->provideTo(
                Eff::clock()->map(fn(Clock $clock) => get_class($clock))
            );
            
            expect($program)->toProduceValue(TestClock::class);
        });

        it('provides convenient clock access methods', function () {
            $testClock = new TestClock(5000);
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $program = $layer->provideTo(Eff::currentTimeMillis());
            
            expect($program)->toProduceValue(5000);
        });

        it('supports clockWith pattern for clock-dependent effects', function () {
            $testClock = new TestClock(1000);
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $program = $layer->provideTo(
                Eff::clockWith(fn(Clock $clock) => 
                    Eff::succeed($clock->currentTimeMillis() * 2)
                )
            );
            
            expect($program)->toProduceValue(2000);
        });
    });

    describe('Time-dependent Effect Patterns', function () {
        it('enables fast testing of sleep effects with TestClock', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $start = microtime(true);
            
            $program = $layer->provideTo(
                Eff::sleepFor(Duration::seconds(60)) // Would take 60 seconds with real clock
                    ->map(fn() => 'completed')
            );
            
            $elapsed = microtime(true) - $start;
            
            expect($program)->toProduceValue('completed');
            expect($elapsed)->toBeLessThan(0.1); // Completes almost instantly
        });

        it('enables testing of time-based effect sequences', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $program = $layer->provideTo(
                Eff::currentTimeMillis()
                    ->flatMap(fn($startTime) => 
                        Eff::sleepFor(Duration::seconds(2))
                            ->flatMap(fn() => Eff::currentTimeMillis())
                            ->map(fn($endTime) => $endTime - $startTime)
                    )
            );
            
            expect($program)->toProduceValue(2000); // 2 seconds in milliseconds
        });

        it('supports deterministic time-based testing', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            $events = [];
            
            // Create a program that captures timestamps at different intervals
            $program = $layer->provideTo(
                Eff::currentTimeMillis()
                    ->flatMap(fn($t1) => 
                        Eff::sleepFor(Duration::seconds(1))
                            ->flatMap(fn() => Eff::currentTimeMillis())
                            ->flatMap(fn($t2) => 
                                Eff::sleepFor(Duration::seconds(2))
                                    ->flatMap(fn() => Eff::currentTimeMillis())
                                    ->map(fn($t3) => [$t1, $t2, $t3])
                            )
                    )
            );
            
            expect($program)->toProduceValue([0, 1000, 3000]);
        });

        it('enables testing of complex timing scenarios', function () {
            $testClock = new TestClock();
            $layer = Layer::fromValue($testClock, Clock::class);
            
            // Simulate a process that measures its own execution time
            $complexProcess = Eff::clockWith(function(Clock $clock) {
                $startTime = $clock->currentTimeMillis();
                return Eff::sleepFor(Duration::seconds(2))
                    ->flatMap(fn() => Eff::clock())
                    ->map(function(Clock $clock) use ($startTime) {
                        $elapsed = $clock->currentTimeMillis() - $startTime;
                        return $elapsed <= 3000 ? 'fast-enough' : 'too-slow';
                    });
            });
            
            $program = $layer->provideTo($complexProcess);
            
            expect($program)->toProduceValue('fast-enough');
        });
    });

    describe('Clock Interface Compliance', function () {
        it('SystemClock implements Clock interface correctly', function () {
            $clock = new SystemClock();
            
            expect($clock)->toBeInstanceOf(Clock::class);
            expect($clock->currentTimeMillis())->toBeInt();
            expect($clock->nanoTime())->toBeInt();
            expect($clock->nanoTime())->toBeGreaterThan(0);
        });

        it('TestClock implements Clock interface correctly', function () {
            $clock = new TestClock(1000);
            
            expect($clock)->toBeInstanceOf(Clock::class);
            expect($clock->currentTimeMillis())->toBe(1000);
            expect($clock->nanoTime())->toBeInt();
        });

        it('Clock sleep method works with continuation pattern', function () {
            $testClock = new TestClock();
            $executed = false;
            
            // Test the continuation-based sleep interface
            // In TestClock, this executes immediately and advances time
            $testClock->sleep(Duration::milliseconds(100), function() use (&$executed) {
                $executed = true;
            });
            
            // In TestClock, sleep executes immediately for fast testing
            expect($executed)->toBeTrue();
            expect($testClock->currentTimeMillis())->toBe(100);
        });
    });
});