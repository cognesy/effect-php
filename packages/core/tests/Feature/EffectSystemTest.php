<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;

describe('Effect System & Core Operations', function () {
    
    describe('composable effect chains', function () {
        it('chains multiple transformations with map', function () {
            $effect = Eff::succeed(10)
                ->map(fn($x) => $x * 2)
                ->map(fn($x) => $x + 5)
                ->map(fn($x) => "Result: $x");
            
            expect($effect)->toProduceValue('Result: 25');
        });
        
        it('chains effects with flatMap for dependent operations', function () {
            $divideBy = fn($divisor) => $divisor === 0 
                ? Eff::fail(new \DivisionByZeroError('Cannot divide by zero'))
                : Eff::succeed(100 / $divisor);
            
            $effect = Eff::succeed(20)
                ->flatMap($divideBy)
                ->map(fn($result) => round($result, 2));
            
            expect($effect)->toProduceValue(5.0);
        });
        
        it('handles nested effect composition', function () {
            $fetchUser = fn($id) => Eff::succeed(['id' => $id, 'name' => "User $id"]);
            $fetchUserPosts = fn($userId) => Eff::succeed(["Post 1 by $userId", "Post 2 by $userId"]);
            
            $effect = $fetchUser(123)
                ->flatMap(fn($user) => 
                    $fetchUserPosts($user['id'])
                        ->map(fn($posts) => ['user' => $user, 'posts' => $posts])
                );
            
            $result = Run::sync($effect);
            
            expect($result['user']['name'])->toBe('User 123')
                ->and($result['posts'])->toHaveCount(2);
        });
    });
    
    describe('lazy evaluation and stack safety', function () {
        it('handles deep effect chains without stack overflow', function () {
            $effect = Eff::succeed(0);
            
            // Create a very deep chain that would cause stack overflow with eager evaluation
            for ($i = 0; $i < 5000; $i++) {
                $effect = $effect->flatMap(fn($x) => Eff::succeed($x + 1));
            }
            
            $result = Run::sync($effect);
            
            expect($result)->toBe(5000);
        });
        
        it('demonstrates lazy evaluation - effects are not executed until run', function () {
            $sideEffect = false;
            
            $effect = Eff::sync(function() use (&$sideEffect) {
                $sideEffect = true;
                return 'executed';
            });
            
            // Effect is created but not executed
            expect($sideEffect)->toBeFalse();
            
            // Only executed when run
            $result = Run::sync($effect);
            expect($sideEffect)->toBeTrue()
                ->and($result)->toBe('executed');
        });
    });
    
    describe('error propagation', function () {
        it('short-circuits on first error in chain', function () {
            $sideEffect = false;
            
            $effect = Eff::succeed(10)
                ->flatMap(fn($x) => Eff::fail(new \RuntimeException('Early failure')))
                ->map(function($x) use (&$sideEffect) {
                    $sideEffect = true; // This should never execute
                    return $x * 2;
                });
            
            expect(fn() => Run::sync($effect))
                ->toThrow(\RuntimeException::class, 'Early failure');
                
            expect($sideEffect)->toBeFalse();
        });
        
        it('preserves error information through chains', function () {
            $originalError = new \InvalidArgumentException('Invalid input', 42);
            
            $effect = Eff::fail($originalError)
                ->map(fn($x) => $x * 2)
                ->flatMap(fn($x) => Eff::succeed($x + 1));
            
            try {
                Run::sync($effect);
                expect(false)->toBeTrue('Should have thrown exception');
            } catch (\InvalidArgumentException $e) {
                expect($e)->toBe($originalError)
                    ->and($e->getCode())->toBe(42);
            }
        });
    });
    
    describe('safe execution patterns', function () {
        it('provides safe execution with Result outcome', function () {
            $successEffect = Eff::succeed('success value');
            $failureEffect = Eff::fail(new \RuntimeException('failure'));
            
            $successResult = Run::syncResult($successEffect);
            $failureResult = Run::syncResult($failureEffect);
            
            expect($successResult->isSuccess())->toBeTrue()
                ->and($successResult->getValueOrNull())->toBe('success value');
                
            expect($failureResult->isFailure())->toBeTrue()
                ->and($failureResult->getErrorOrNull())->toBeInstanceOf(\RuntimeException::class);
        });
        
        it('handles synchronous computations that may throw', function () {
            $safeComputation = Eff::sync(fn() => json_decode('{"valid": "json"}', true));
            $unsafeComputation = Eff::sync(fn() => throw new \RuntimeException('Computation failed'));
            
            expect($safeComputation)->toProduceValue(['valid' => 'json']);
            expect($unsafeComputation)->toFailWith(\RuntimeException::class);
        });
    });
    
    describe('library developer scenarios', function () {
        it('models HTTP request processing pipeline', function () {
            // Simulate HTTP request processing for InstructorPHP/PolyglotPHP
            $validateRequest = fn($data) => 
                empty($data['prompt']) 
                    ? Eff::fail(new \InvalidArgumentException('Prompt is required'))
                    : Eff::succeed($data);
            
            $callLLMApi = fn($data) => Eff::sync(function() use ($data) {
                // Simulate API call
                return [
                    'choices' => [
                        ['message' => ['content' => json_encode(['result' => 'parsed data'])]]
                    ]
                ];
            });
            
            $parseResponse = fn($response) => Eff::sync(function() use ($response) {
                $content = $response['choices'][0]['message']['content'];
                $parsed = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON response');
                }
                return $parsed;
            });
            
            $validateOutput = fn($parsed) => 
                isset($parsed['result']) 
                    ? Eff::succeed($parsed)
                    : Eff::fail(new \RuntimeException('Invalid output structure'));
            
            // Complete pipeline
            $processRequest = fn($requestData) =>
                $validateRequest($requestData)
                    ->flatMap($callLLMApi)
                    ->flatMap($parseResponse)
                    ->flatMap($validateOutput);
            
            $result = Run::syncResult($processRequest(['prompt' => 'test prompt']));
            
            expect($result->isSuccess())->toBeTrue();
            $output = $result->getValueOrNull();
            expect($output['result'])->toBe('parsed data');
        });
        
        it('demonstrates multi-provider API abstraction', function () {
            // Simulate PolyglotPHP multi-provider pattern
            interface LLMProvider {
                public function complete(array $params): array;
            }
            
            $createProvider = fn($type) => match($type) {
                'openai' => new class implements LLMProvider {
                    public function complete(array $params): array {
                        return ['content' => "OpenAI: {$params['prompt']}"];
                    }
                },
                'anthropic' => new class implements LLMProvider {
                    public function complete(array $params): array {
                        return ['content' => "Anthropic: {$params['prompt']}"];
                    }
                },
                default => throw new \InvalidArgumentException("Unknown provider: $type")
            };
            
            $callProvider = fn($provider, $params) => Eff::sync(function() use ($provider, $params) {
                return $provider->complete($params);
            });
            
            $processWithProvider = fn($providerType, $prompt) =>
                Eff::sync(fn() => $createProvider($providerType))
                    ->flatMap(fn($provider) => $callProvider($provider, ['prompt' => $prompt]))
                    ->map(fn($response) => ['provider' => $providerType, 'response' => $response]);
            
            $openaiResult = Run::sync($processWithProvider('openai', 'Hello'));
            $anthropicResult = Run::sync($processWithProvider('anthropic', 'Hello'));
            
            expect($openaiResult['provider'])->toBe('openai')
                ->and($openaiResult['response']['content'])->toContain('OpenAI: Hello');
                
            expect($anthropicResult['provider'])->toBe('anthropic')
                ->and($anthropicResult['response']['content'])->toContain('Anthropic: Hello');
        });
    });
});