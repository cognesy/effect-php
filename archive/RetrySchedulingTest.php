<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Runtime\Clock\TestClock;
use EffectPHP\Core\Schedule\Schedule;
use EffectPHP\Core\Utils\Duration;

describe('Retry and Scheduling Operations', function () {
    
    describe('basic retry patterns', function () {
        it('retries failed operations with fixed delay', function () {
            $attempt = 0;
            $flakyOperation = function() use (&$attempt) {
                $attempt++;
                if ($attempt < 3) {
                    throw new \RuntimeException("Attempt $attempt failed");
                }
                return "Success on attempt $attempt";
            };
            
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $effect = Eff::sync($flakyOperation)
                ->retryWith(Schedule::fixedDelay(Duration::milliseconds(100))->upToMaxRetries(5));
            
            $result = Eff::runSync($testLayer->provideTo($effect));
            
            expect($result)->toProduceValue('Success on attempt 3')
                ->and($attempt)->toBe(3);
        });
        
        it('respects maximum retry attempts', function () {
            $attempt = 0;
            $alwaysFailingOperation = function() use (&$attempt) {
                $attempt++;
                throw new \RuntimeException("Attempt $attempt failed");
            };
            
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $effect = Eff::sync($alwaysFailingOperation)
                ->retryWith(Schedule::fixedDelay(Duration::milliseconds(100))->upToMaxRetries(2));
            
            $result = Eff::runSafely($testLayer->provideTo($effect));
            
            expect($result->isLeft())->toBeTrue()
                ->and($attempt)->toBe(3); // Initial attempt + 2 retries
        });
    });
    
    describe('exponential backoff patterns', function () {
        it('implements exponential backoff with jitter', function () {
            $attempt = 0;
            $testClock = new TestClock();
            
            $operation = function() use (&$attempt, $testClock) {
                $attempt++;
                if ($attempt < 4) {
                    throw new \RuntimeException("Attempt $attempt at time {$testClock->currentTimeMillis()}");
                }
                return "Success on attempt $attempt";
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $schedule = Schedule::exponentialBackoff(Duration::milliseconds(100))
                ->upToMaxRetries(5)
                ->withJitter(0.1);
            
            $effect = Eff::sync($operation)->retryWith($schedule);
            
            $result = Eff::runSync($testLayer->provideTo($effect));
            
            expect($result)->toProduceValue('Success on attempt 4')
                ->and($attempt)->toBe(4);
        });
        
        it('handles fibonacci backoff progression', function () {
            $attempt = 0;
            $delays = [];
            $testClock = new TestClock();
            
            $operation = function() use (&$attempt, &$delays, $testClock) {
                $currentTime = $testClock->currentTimeMillis();
                if ($attempt > 0) {
                    $delays[] = $currentTime;
                }
                $attempt++;
                
                if ($attempt < 5) {
                    throw new \RuntimeException("Attempt $attempt");
                }
                return "Success";
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $schedule = Schedule::fibonacciBackoff(Duration::milliseconds(100))
                ->upToMaxRetries(6);
            
            $effect = Eff::sync($operation)->retryWith($schedule);
            
            $result = Eff::runSync($testLayer->provideTo($effect));
            
            expect($result)->toProduceValue('Success')
                ->and($attempt)->toBe(5);
        });
    });
    
    describe('time-based constraints', function () {
        it('respects maximum duration limits', function () {
            $attempt = 0;
            $testClock = new TestClock();
            
            $slowOperation = function() use (&$attempt, $testClock) {
                $attempt++;
                // Advance clock to simulate time passing
                $testClock->advance(Duration::milliseconds(150));
                throw new \RuntimeException("Attempt $attempt");
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $schedule = Schedule::fixedDelay(Duration::milliseconds(50))
                ->upToMaxDuration(Duration::milliseconds(400));
            
            $effect = Eff::sync($slowOperation)->retryWith($schedule);
            
            $result = Eff::runSafely($testLayer->provideTo($effect));
            
            expect($result->isLeft())->toBeTrue()
                ->and($attempt)->toBeLessThan(10); // Should stop due to time limit, not retry limit
        });
    });
    
    describe('conditional retry logic', function () {
        it('retries only specific error types', function () {
            $attempt = 0;
            $operation = function() use (&$attempt) {
                $attempt++;
                if ($attempt === 1) {
                    throw new \RuntimeException('Retryable error');
                }
                if ($attempt === 2) {
                    throw new \InvalidArgumentException('Non-retryable error');
                }
                return 'Success';
            };
            
            $testClock = new TestClock();
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $retrySchedule = Schedule::fixedDelay(Duration::milliseconds(100))
                ->upToMaxRetries(5)
                ->retryIf(fn(\Throwable $e) => $e instanceof \RuntimeException);
            
            $effect = Eff::sync($operation)->retryWith($retrySchedule);
            
            $result = Eff::runSafely($testLayer->provideTo($effect));
            
            expect($result->isLeft())->toBeTrue()
                ->and($attempt)->toBe(2); // Should stop at InvalidArgumentException
            
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error)->toBeInstanceOf(\InvalidArgumentException::class);
        });
    });
    
    describe('library developer scenarios', function () {
        it('handles HTTP API rate limiting with exponential backoff', function () {
            // Simulate rate-limited API like OpenAI
            $requestCount = 0;
            $testClock = new TestClock();
            
            $rateLimitedApi = function() use (&$requestCount) {
                $requestCount++;
                
                if ($requestCount <= 2) {
                    $exception = new \RuntimeException('Rate limit exceeded', 429);
                    throw $exception;
                }
                
                return [
                    'choices' => [
                        ['message' => ['content' => 'API response after retries']]
                    ]
                ];
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $retryableErrors = fn(\Throwable $e) => $e->getCode() >= 500 || $e->getCode() === 429;
            
            $schedule = Schedule::exponentialBackoff(Duration::milliseconds(1000))
                ->upToMaxRetries(3)
                ->retryIf($retryableErrors);
            
            $apiCall = Eff::sync($rateLimitedApi)->retryWith($schedule);
            
            $result = Eff::runSync($testLayer->provideTo($apiCall));
            
            expect($result)->toProduceValue(['choices' => [['message' => ['content' => 'API response after retries']]]])
                ->and($requestCount)->toBe(3);
        });
        
        it('implements circuit breaker pattern with time-based recovery', function () {
            $failureCount = 0;
            $testClock = new TestClock();
            
            $unreliableService = function() use (&$failureCount, $testClock) {
                $currentTime = $testClock->currentTimeMillis();
                
                // Fail for first 3 attempts, then succeed
                if ($failureCount < 3) {
                    $failureCount++;
                    throw new \RuntimeException("Service failure $failureCount at time $currentTime");
                }
                
                return "Service recovered at time $currentTime";
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            // Circuit breaker: exponential backoff with max duration
            $circuitBreakerSchedule = Schedule::exponentialBackoff(Duration::milliseconds(500))
                ->upToMaxRetries(5)
                ->upToMaxDuration(Duration::seconds(10));
            
            $serviceCall = Eff::sync($unreliableService)->retryWith($circuitBreakerSchedule);
            
            $result = Eff::runSync($testLayer->provideTo($serviceCall));
            
            expect($result)->toProduceValue(expect($result)->toContain('Service recovered'))
                ->and($failureCount)->toBe(3);
        });
        
        it('handles database transaction retries', function () {
            // Simulate database deadlock scenario
            $transactionAttempt = 0;
            $testClock = new TestClock();
            
            $databaseTransaction = function() use (&$transactionAttempt) {
                $transactionAttempt++;
                
                // Simulate deadlock on first two attempts
                if ($transactionAttempt <= 2) {
                    throw new \PDOException("Deadlock found when trying to get lock; try restarting transaction");
                }
                
                return ['transaction_id' => "tx_$transactionAttempt", 'status' => 'committed'];
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $deadlockRetry = fn(\Throwable $e) => 
                $e instanceof \PDOException && str_contains($e->getMessage(), 'Deadlock');
            
            $schedule = Schedule::linearBackoff(Duration::milliseconds(100))
                ->upToMaxRetries(5)
                ->retryIf($deadlockRetry);
            
            $transaction = Eff::sync($databaseTransaction)->retryWith($schedule);
            
            $result = Eff::runSync($testLayer->provideTo($transaction));
            
            expect($result)->toProduceValue(['transaction_id' => 'tx_3', 'status' => 'committed'])
                ->and($transactionAttempt)->toBe(3);
        });
        
        it('implements intelligent retry for streaming operations', function () {
            // Simulate streaming LLM response that may be interrupted
            $streamAttempt = 0;
            $testClock = new TestClock();
            
            $streamingOperation = function() use (&$streamAttempt, $testClock) {
                $streamAttempt++;
                $currentTime = $testClock->currentTimeMillis();
                
                // Simulate connection interruption
                if ($streamAttempt === 1) {
                    throw new \RuntimeException("Connection interrupted at byte 1024");
                }
                
                // Simulate timeout on second attempt
                if ($streamAttempt === 2) {
                    throw new \RuntimeException("Read timeout after 30 seconds");
                }
                
                return [
                    'stream_id' => "stream_$streamAttempt",
                    'content' => 'Complete streaming response',
                    'completed_at' => $currentTime
                ];
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $retryableStreamErrors = fn(\Throwable $e) => 
                str_contains($e->getMessage(), 'interrupted') || 
                str_contains($e->getMessage(), 'timeout');
            
            $streamSchedule = Schedule::exponentialBackoff(Duration::milliseconds(200))
                ->upToMaxRetries(4)
                ->withJitter(0.2)
                ->retryIf($retryableStreamErrors);
            
            $streamEffect = Eff::sync($streamingOperation)->retryWith($streamSchedule);
            
            $result = Eff::runSync($testLayer->provideTo($streamEffect));
            
            expect($result)->toProduceValue(expect($result)->toHaveKey('stream_id'))
                ->and($streamAttempt)->toBe(3);
        });
        
        it('combines retries with timeouts for robust API calls', function () {
            $apiAttempt = 0;
            $testClock = new TestClock();
            
            $slowApi = function() use (&$apiAttempt, $testClock) {
                $apiAttempt++;
                
                if ($apiAttempt === 1) {
                    // Simulate slow response that times out
                    $testClock->adjust(Duration::seconds(35));
                    throw new \RuntimeException('Request timeout after 30 seconds');
                }
                
                return ['data' => 'API response', 'attempt' => $apiAttempt];
            };
            
            $testLayer = Layer::fromValue($testClock, Clock::class);
            
            $timeoutRetry = fn(\Throwable $e) => str_contains($e->getMessage(), 'timeout');
            
            $apiWithRetry = Eff::sync($slowApi)
                ->retryWith(
                    Schedule::exponentialBackoff(Duration::milliseconds(1000))
                        ->upToMaxRetries(2)
                        ->retryIf($timeoutRetry)
                );
            
            $result = Eff::runSync($testLayer->provideTo($apiWithRetry));
            
            expect($result)->toProduceValue(['data' => 'API response', 'attempt' => 2])
                ->and($apiAttempt)->toBe(2);
        });
    });
});