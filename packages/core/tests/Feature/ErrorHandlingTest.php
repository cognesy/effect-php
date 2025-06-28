<?php

declare(strict_types=1);

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Eff;

describe('Structured Error Handling', function () {
    
    describe('rich error composition', function () {
        it('composes multiple parallel errors', function () {
            $effects = [
                Eff::fail(new \InvalidArgumentException('Validation error 1')),
                Eff::succeed('success'),
                Eff::fail(new \RuntimeException('Runtime error 2')),
                Eff::fail(new \LogicException('Logic error 3'))
            ];
            
            $parallelEffect = Eff::allInParallel($effects);
            
            try {
                Eff::runSync($parallelEffect);
                expect(false)->toBeTrue('Should have thrown exception');
            } catch (\Throwable $e) {
                // First error is thrown, but Cause system should track all errors
                expect($e)->toBeInstanceOf(\InvalidArgumentException::class);
            }
        });
        
        it('maintains error context through sequential operations', function () {
            $step1 = Eff::succeed('input');
            $step2 = fn($input) => Eff::fail(new \RuntimeException("Step 2 failed with: $input"));
            $step3 = fn($input) => Eff::succeed("Step 3: $input");
            
            $pipeline = $step1
                ->flatMap($step2)
                ->flatMap($step3);
            
            expect($pipeline)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('type-safe error recovery', function () {
        it('catches specific error types with recovery', function () {
            $riskyOperation = Eff::sync(function() {
                throw new \InvalidArgumentException('Invalid input provided');
            });
            
            $withRecovery = $riskyOperation
                ->catchError(\InvalidArgumentException::class, fn($e) => 
                    Eff::succeed("Recovered from: {$e->getMessage()}")
                )
                ->catchError(\RuntimeException::class, fn($e) => 
                    Eff::succeed("Runtime recovery: {$e->getMessage()}")
                );
            
            expect($withRecovery)->toProduceValue('Recovered from: Invalid input provided');
        });
        
        it('allows fallback chains for different error types', function () {
            $networkCall = fn($shouldTimeout) => $shouldTimeout
                ? Eff::fail(new \RuntimeException('Network timeout'))
                : Eff::fail(new \InvalidArgumentException('Bad request'));
            
            $withFallbacks = $networkCall(true)
                ->catchError(\InvalidArgumentException::class, fn($e) => 
                    Eff::succeed('Used cached data due to bad request')
                )
                ->catchError(\RuntimeException::class, fn($e) => 
                    Eff::succeed('Used cached data due to network error')
                );
            
            expect($withFallbacks)->toProduceValue('Used cached data due to network error');
        });
        
        it('supports conditional error handling', function () {
            $createError = fn($code) => new \RuntimeException("Error code: $code", $code);
            
            $retryableErrors = fn(\Throwable $e) => $e->getCode() >= 500 && $e->getCode() < 600;
            $nonRetryableErrors = fn(\Throwable $e) => $e->getCode() >= 400 && $e->getCode() < 500;
            
            $operation = Eff::fail($createError(503));
            
            $withConditionalHandling = $operation
                ->catchError($retryableErrors, fn($e) => 
                    Eff::succeed("Will retry error: {$e->getCode()}")
                )
                ->catchError($nonRetryableErrors, fn($e) => 
                    Eff::succeed("Won't retry error: {$e->getCode()}")
                );
            
            expect($withConditionalHandling)->toProduceValue('Will retry error: 503');
        });
    });
    
    describe('error transformation and enhancement', function () {
        it('transforms errors with additional context', function () {
            $operation = Eff::sync(fn() => throw new \RuntimeException('Database connection failed'));
            
            $withContext = $operation->catchError(\RuntimeException::class, function($e) {
                $enhanced = new \RuntimeException(
                    "Operation failed in UserService: {$e->getMessage()}", 
                    $e->getCode(), 
                    $e
                );
                return Eff::fail($enhanced);
            });
            
            try {
                Eff::runSync($withContext);
                expect(false)->toBeTrue('Should have thrown exception');
            } catch (\RuntimeException $e) {
                expect($e->getMessage())->toContain('Operation failed in UserService')
                    ->and($e->getPrevious())->not->toBeNull()
                    ->and($e->getPrevious()->getMessage())->toBe('Database connection failed');
            }
        });
        
        it('converts exceptions to domain-specific errors', function () {
            class ValidationError extends \Exception {}
            class BusinessLogicError extends \Exception {}
            
            $databaseOperation = Eff::sync(fn() => throw new \PDOException('Connection timeout'));
            
            $withDomainErrors = $databaseOperation
                ->catchError(\PDOException::class, fn($e) => 
                    Eff::fail(new BusinessLogicError("Data access failed: {$e->getMessage()}"))
                );
            
            expect($withDomainErrors)->toFailWith(BusinessLogicError::class);
        });
    });
    
    describe('library developer scenarios', function () {
        it('handles multi-stage API validation errors', function () {
            // Simulate InstructorPHP validation pipeline
            $validateApiKey = fn($config) => 
                empty($config['api_key']) 
                    ? Eff::fail(new \InvalidArgumentException('API key is required'))
                    : Eff::succeed($config);
            
            $validateModel = fn($config) => 
                empty($config['model']) 
                    ? Eff::fail(new \InvalidArgumentException('Model is required'))
                    : Eff::succeed($config);
            
            $validatePrompt = fn($input) => 
                empty($input['prompt']) 
                    ? Eff::fail(new \InvalidArgumentException('Prompt is required'))
                    : Eff::succeed($input);
            
            $validateAll = fn($config, $input) => Eff::allInParallel([
                $validateApiKey($config),
                $validateModel($config),
                $validatePrompt($input)
            ])->map(fn() => ['config' => $config, 'input' => $input]);
            
            // Test with missing API key
            $invalidConfig = ['model' => 'gpt-4'];
            $validInput = ['prompt' => 'Hello'];
            
            $result = $validateAll($invalidConfig, $validInput);
            
            expect($result)->toFailWith(\InvalidArgumentException::class);
        });
        
        it('handles LLM API response parsing errors', function () {
            // Simulate PolyglotPHP response processing
            $mockApiResponse = '{"choices": [{"message": {"content": "invalid json content"}}]}';
            
            $parseApiResponse = fn($response) => Eff::sync(function() use ($response) {
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON response from API');
                }
                return $data;
            });
            
            $extractContent = fn($data) => 
                isset($data['choices'][0]['message']['content'])
                    ? Eff::succeed($data['choices'][0]['message']['content'])
                    : Eff::fail(new \RuntimeException('Invalid response structure'));
            
            $parseStructuredOutput = fn($content) => Eff::sync(function() use ($content) {
                $parsed = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('LLM returned invalid JSON: ' . $content);
                }
                return $parsed;
            });
            
            $processResponse = $parseApiResponse($mockApiResponse)
                ->flatMap($extractContent)
                ->flatMap($parseStructuredOutput)
                ->catchError(\RuntimeException::class, function($e) {
                    if (str_contains($e->getMessage(), 'LLM returned invalid JSON')) {
                        return Eff::succeed(['error' => 'INVALID_LLM_OUTPUT', 'raw' => $e->getMessage()]);
                    }
                    return Eff::fail($e);
                });
            
            $result = Eff::runSync($processResponse);
            
            expect($result['error'])->toBe('INVALID_LLM_OUTPUT')
                ->and($result['raw'])->toContain('invalid json content');
        });
        
        it('handles rate limiting and retry scenarios', function () {
            // Simulate API rate limiting
            $attempt = 0;
            $rateLimitedApi = function() use (&$attempt) {
                $attempt++;
                if ($attempt < 3) {
                    throw new \RuntimeException('Rate limit exceeded', 429);
                }
                return ['success' => true, 'attempts' => $attempt];
            };
            
            $apiCall = Eff::sync($rateLimitedApi);
            
            $withRetry = $apiCall->catchError(function(\Throwable $e) {
                return $e->getCode() === 429;
            }, function($e) use (&$apiCall) {
                // Simulate exponential backoff retry
                return $apiCall->catchError(function(\Throwable $e) {
                    return $e->getCode() === 429;
                }, function($e) use (&$apiCall) {
                    return $apiCall; // Final retry
                });
            });
            
            $result = Eff::runSync($withRetry);
            
            expect($result['success'])->toBeTrue()
                ->and($result['attempts'])->toBe(3);
        });
        
        it('demonstrates structured error logging', function () {
            $logger = new class {
                public array $logs = [];
                public function error(string $message, array $context = []): void {
                    $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
                }
            };
            
            $failingOperation = Eff::sync(fn() => throw new \RuntimeException('Service unavailable'));
            
            $withLogging = $failingOperation->catchError(\RuntimeException::class, function($e) use ($logger) {
                $logger->error('Operation failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString()
                ]);
                return Eff::succeed('Used fallback');
            });
            
            $result = Eff::runSync($withLogging);
            
            expect($result)->toBe('Used fallback')
                ->and($logger->logs)->toHaveCount(1)
                ->and($logger->logs[0]['message'])->toBe('Operation failed')
                ->and($logger->logs[0]['context']['error'])->toBe('Service unavailable');
        });
    });
});