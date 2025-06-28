<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Runtime\Clock\TestClock;
use EffectPHP\Core\Schedule\Schedule;
use EffectPHP\Core\Utils\Duration;

describe('Time Control and Testing Support', function () {
    
    describe('virtual time control', function () {
        it('allows fast execution of time-dependent operations', function () {
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $timeBasedOperation = Eff::service(Clock::class)
                ->flatMap(fn($clock) => $clock->currentTimeMillis())
                ->flatMap(function($startTime) use ($testClock) {
                    // Advance virtual time instead of real waiting
                    $testClock->adjust(Duration::seconds(10));
                    return Eff::service(Clock::class)
                        ->flatMap(fn($clock) => $clock->currentTimeMillis())
                        ->map(fn($endTime) => $endTime - $startTime);
                });
            
            $result = Eff::runSync($testLayer->provideTo($timeBasedOperation));
            
            expect($result)->toBe(10000); // 10 seconds in milliseconds
        });
        
        it('enables deterministic testing of sleep operations', function () {
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $workflow = Eff::service(Clock::class)
                ->flatMap(fn($clock) => $clock->currentTimeMillis())
                ->flatMap(function($start) {
                    return Eff::sleepFor(Duration::seconds(5))
                        ->flatMap(fn() => Eff::service(Clock::class))
                        ->flatMap(fn($clock) => $clock->currentTimeMillis())
                        ->map(fn($end) => ['start' => $start, 'end' => $end, 'elapsed' => $end - $start]);
                });
            
            $result = Eff::runSync($testLayer->provideTo($workflow));
            
            expect($result['elapsed'])->toBe(5000)
                ->and($result['end'])->toBeGreaterThan($result['start']);
        });
    });
    
    describe('timeout testing', function () {
        it('simulates timeout scenarios with virtual time', function () {
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $slowOperation = Eff::sleepFor(Duration::seconds(10))
                ->map(fn() => 'Operation completed');
            
            $fastTimeout = Eff::sleepFor(Duration::seconds(5))
                ->flatMap(fn() => Eff::fail(new \RuntimeException('Operation timed out')));
            
            $raceResult = Eff::raceAll([$slowOperation, $fastTimeout]);
            
            $result = Eff::runSafely($testLayer->provideTo($raceResult));
            
            expect($result->isLeft())->toBeTrue();
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error->getMessage())->toBe('Operation timed out');
        });
        
        it('tests complex timing scenarios', function () {
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $events = [];
            
            $task1 = Eff::sleepFor(Duration::milliseconds(100))
                ->map(function() use (&$events) {
                    $events[] = 'task1_completed';
                    return 'task1';
                });
            
            $task2 = Eff::sleepFor(Duration::milliseconds(200))
                ->map(function() use (&$events) {
                    $events[] = 'task2_completed';
                    return 'task2';
                });
            
            $task3 = Eff::sleepFor(Duration::milliseconds(150))
                ->map(function() use (&$events) {
                    $events[] = 'task3_completed';
                    return 'task3';
                });
            
            $parallelTasks = Eff::allInParallel([$task1, $task2, $task3]);
            
            $result = Eff::runSync($testLayer->provideTo($parallelTasks));
            
            expect($result)->toBe(['task1', 'task2', 'task3'])
                ->and($events)->toBe(['task1_completed', 'task2_completed', 'task3_completed']);
        });
    });
    
    describe('schedule testing with virtual time', function () {
        it('tests retry schedules with fast virtual time progression', function () {
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $attempt = 0;
            $operation = function() use (&$attempt, $testClock) {
                $attempt++;
                $currentTime = $testClock->currentTimeMillis();
                
                if ($attempt < 4) {
                    throw new \RuntimeException("Attempt $attempt failed at time $currentTime");
                }
                
                return "Success on attempt $attempt at time $currentTime";
            };
            
            $retrySchedule = Schedule::exponentialBackoff(Duration::milliseconds(100))
                ->upToMaxRetries(5);
            
            $workflow = Eff::sync($operation)->retryWith($retrySchedule);
            
            $result = Eff::runSync($testLayer->provideTo($workflow));
            
            expect($result)->toContain('Success on attempt 4')
                ->and($attempt)->toBe(4);
        });
        
        it('verifies schedule timing accuracy', function () {
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $retryTimes = [];
            $attempt = 0;
            
            $operation = function() use (&$attempt, &$retryTimes, $testClock) {
                $attempt++;
                $retryTimes[] = $testClock->currentTimeMillis();
                
                if ($attempt < 4) {
                    throw new \RuntimeException("Attempt $attempt");
                }
                
                return 'Success';
            };
            
            // Fixed delay of 500ms between retries
            $schedule = Schedule::fixedDelay(Duration::milliseconds(500))
                ->upToMaxRetries(5);
            
            $workflow = Eff::sync($operation)->retryWith($schedule);
            
            $result = Eff::runSync($testLayer->provideTo($workflow));
            
            expect($result)->toBe('Success')
                ->and($retryTimes)->toHaveCount(4);
            
            // Verify timing intervals (500ms apart)
            expect($retryTimes[1] - $retryTimes[0])->toBe(500)
                ->and($retryTimes[2] - $retryTimes[1])->toBe(500)
                ->and($retryTimes[3] - $retryTimes[2])->toBe(500);
        });
    });
    
    describe('library developer scenarios', function () {
        it('tests API rate limiting with virtual time', function () {
            // Simulate rate-limited API testing for PolyglotPHP
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $apiCalls = [];
            $rateLimitedApi = function($request) use (&$apiCalls, $testClock) {
                $currentTime = $testClock->currentTimeMillis();
                $apiCalls[] = ['time' => $currentTime, 'request' => $request];
                
                // Simulate rate limit: max 2 calls per second
                $recentCalls = array_filter($apiCalls, fn($call) => 
                    $currentTime - $call['time'] < 1000
                );
                
                if (count($recentCalls) > 2) {
                    throw new \RuntimeException('Rate limit exceeded', 429);
                }
                
                return ['response' => "API response for {$request} at time {$currentTime}"];
            };
            
            $makeApiCall = fn($request) => Eff::sync(fn() => $rateLimitedApi($request));
            
            $rateLimitedWorkflow = $makeApiCall('request1')
                ->flatMap(fn($response1) => 
                    Eff::sleepFor(Duration::milliseconds(600)) // Wait to avoid rate limit
                        ->flatMap(fn() => $makeApiCall('request2'))
                        ->map(fn($response2) => [$response1, $response2])
                );
            
            $result = Eff::runSync($testLayer->provideTo($rateLimitedWorkflow));
            
            expect($result[0]['response'])->toContain('request1')
                ->and($result[1]['response'])->toContain('request2')
                ->and(count($apiCalls))->toBe(2);
        });
        
        it('tests streaming timeout scenarios', function () {
            // Simulate streaming response timeout testing for InstructorPHP
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $streamingResponse = function($timeoutAfter) {
                return Eff::service(Clock::class)
                    ->flatMap(fn($clock) => $clock->currentTimeMillis())
                    ->flatMap(function($startTime) use ($timeoutAfter) {
                        return Eff::sleepFor($timeoutAfter)
                            ->flatMap(fn() => Eff::service(Clock::class))
                            ->flatMap(fn($clock) => $clock->currentTimeMillis())
                            ->flatMap(function($endTime) use ($startTime) {
                                $elapsed = $endTime - $startTime;
                                if ($elapsed >= 5000) { // 5 second timeout
                                    return Eff::fail(new \RuntimeException('Streaming timeout'));
                                }
                                return Eff::succeed(['chunks' => ['chunk1', 'chunk2'], 'elapsed' => $elapsed]);
                            });
                    });
            };
            
            // Test successful streaming (under timeout)
            $fastStream = $streamingResponse(Duration::seconds(3));
            $fastResult = Eff::runSafely($testLayer->provideTo($fastStream));
            
            expect($fastResult->isRight())->toBeTrue();
            $fastData = $fastResult->fold(fn($l) => null, fn($r) => $r);
            expect($fastData['chunks'])->toBe(['chunk1', 'chunk2'])
                ->and($fastData['elapsed'])->toBe(3000);
            
            // Test timeout scenario
            $slowStream = $streamingResponse(Duration::seconds(6));
            $slowResult = Eff::runSafely($testLayer->provideTo($slowStream));
            
            expect($slowResult->isLeft())->toBeTrue();
            $error = $slowResult->fold(fn($l) => $l, fn($r) => null);
            expect($error->getMessage())->toBe('Streaming timeout');
        });
        
        it('tests scheduled task execution', function () {
            // Simulate background task scheduling
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $executedTasks = [];
            
            $scheduleTask = function($taskName, $delay) use (&$executedTasks, $testClock) {
                return Eff::sleepFor($delay)
                    ->flatMap(fn() => Eff::service(Clock::class))
                    ->flatMap(fn($clock) => $clock->currentTimeMillis())
                    ->map(function($executionTime) use ($taskName, &$executedTasks) {
                        $executedTasks[] = ['task' => $taskName, 'time' => $executionTime];
                        return "$taskName executed at $executionTime";
                    });
            };
            
            $scheduledTasks = Eff::allInParallel([
                $scheduleTask('daily_cleanup', Duration::hours(24)),
                $scheduleTask('hourly_sync', Duration::hours(1)),
                $scheduleTask('minute_check', Duration::minutes(1))
            ]);
            
            $result = Eff::runSync($testLayer->provideTo($scheduledTasks));
            
            expect($result)->toHaveCount(3)
                ->and($executedTasks)->toHaveCount(3);
            
            // Verify execution order (by time)
            usort($executedTasks, fn($a, $b) => $a['time'] <=> $b['time']);
            expect($executedTasks[0]['task'])->toBe('minute_check')
                ->and($executedTasks[1]['task'])->toBe('hourly_sync')
                ->and($executedTasks[2]['task'])->toBe('daily_cleanup');
        });
        
        it('tests complex timing interactions', function () {
            // Simulate complex LLM processing with timeouts and retries
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $processingSteps = [];
            
            $llmProcessingPipeline = function($input) use (&$processingSteps, $testClock) {
                $recordStep = function($step) use (&$processingSteps, $testClock) {
                    $processingSteps[] = ['step' => $step, 'time' => $testClock->currentTimeMillis()];
                };
                
                // Step 1: Validate input (fast)
                $validate = Eff::sleepFor(Duration::milliseconds(50))
                    ->map(function() use ($input, $recordStep) {
                        $recordStep('validation');
                        if (empty($input)) {
                            throw new \InvalidArgumentException('Invalid input');
                        }
                        return $input;
                    });
                
                // Step 2: Call LLM API (may timeout)
                $callLLM = fn($validInput) => Eff::sleepFor(Duration::seconds(2))
                    ->map(function() use ($validInput, $recordStep) {
                        $recordStep('llm_call');
                        return ['llm_response' => "Processed: $validInput"];
                    });
                
                // Step 3: Parse response (fast)
                $parseResponse = fn($response) => Eff::sleepFor(Duration::milliseconds(100))
                    ->map(function() use ($response, $recordStep) {
                        $recordStep('parsing');
                        return ['parsed' => $response['llm_response']];
                    });
                
                return $validate
                    ->flatMap($callLLM)
                    ->flatMap($parseResponse);
            };
            
            $input = 'test prompt';
            $pipeline = $llmProcessingPipeline($input);
            
            $result = Eff::runSync($testLayer->provideTo($pipeline));
            
            expect($result['parsed'])->toBe('Processed: test prompt')
                ->and($processingSteps)->toHaveCount(3);
            
            // Verify step timing
            expect($processingSteps[0]['step'])->toBe('validation')
                ->and($processingSteps[1]['step'])->toBe('llm_call')
                ->and($processingSteps[2]['step'])->toBe('parsing');
            
            // Verify timing intervals
            expect($processingSteps[1]['time'] - $processingSteps[0]['time'])->toBe(50)
                ->and($processingSteps[2]['time'] - $processingSteps[1]['time'])->toBe(2000);
        });
        
        it('tests virtual time with real-world scheduling patterns', function () {
            // Test realistic scheduling patterns for API management
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $events = [];
            
            $recordEvent = function($event) use (&$events, $testClock) {
                $events[] = ['event' => $event, 'time' => $testClock->currentTimeMillis()];
            };
            
            // Simulate different types of scheduled operations
            $operations = [
                // Rate limit reset every second
                Eff::sleepFor(Duration::seconds(1))
                    ->map(fn() => $recordEvent('rate_limit_reset')),
                
                // Health check every 30 seconds  
                Eff::sleepFor(Duration::seconds(30))
                    ->map(fn() => $recordEvent('health_check')),
                
                // Token refresh every 5 minutes
                Eff::sleepFor(Duration::minutes(5))
                    ->map(fn() => $recordEvent('token_refresh')),
                
                // Cache cleanup every hour
                Eff::sleepFor(Duration::hours(1))
                    ->map(fn() => $recordEvent('cache_cleanup'))
            ];
            
            $scheduledOperations = Eff::allInParallel($operations);
            
            $result = Eff::runSync($testLayer->provideTo($scheduledOperations));
            
            expect($events)->toHaveCount(4);
            
            // Sort by execution time
            usort($events, fn($a, $b) => $a['time'] <=> $b['time']);
            
            expect($events[0]['event'])->toBe('rate_limit_reset')
                ->and($events[1]['event'])->toBe('health_check')
                ->and($events[2]['event'])->toBe('token_refresh')
                ->and($events[3]['event'])->toBe('cache_cleanup');
            
            // Verify exact timing
            expect($events[0]['time'])->toBe(1000)     // 1 second
                ->and($events[1]['time'])->toBe(30000)   // 30 seconds
                ->and($events[2]['time'])->toBe(300000)  // 5 minutes
                ->and($events[3]['time'])->toBe(3600000); // 1 hour
        });
    });
});