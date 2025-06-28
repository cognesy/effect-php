<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Core\Either;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Option;
use EffectPHP\Core\Result\Failure;

describe('Multi-stage Processing Pipeline', function () {
    
    describe('sequential processing stages', function () {
        it('chains multiple validation and transformation stages', function () {
            // Simulate data processing pipeline
            $validateInput = fn($data) => 
                empty($data['input']) 
                    ? Eff::fail(new \InvalidArgumentException('Input is required'))
                    : Eff::succeed($data);
            
            $normalizeData = fn($data) => Eff::sync(function() use ($data) {
                return [
                    ...$data,
                    'input' => trim(strtolower($data['input'])),
                    'normalized' => true
                ];
            });
            
            $enrichData = fn($data) => Eff::sync(function() use ($data) {
                return [
                    ...$data,
                    'word_count' => str_word_count($data['input']),
                    'enriched' => true
                ];
            });
            
            $validateOutput = fn($data) => 
                $data['word_count'] > 0 
                    ? Eff::succeed($data)
                    : Eff::fail(new \RuntimeException('Processed data is invalid'));
            
            $pipeline = fn($input) =>
                $validateInput($input)
                    ->flatMap($normalizeData)
                    ->flatMap($enrichData)
                    ->flatMap($validateOutput);
            
            $result = Run::sync($pipeline(['input' => '  Hello World  ']));
            
            expect($result['input'])->toBe('hello world')
                ->and($result['normalized'])->toBeTrue()
                ->and($result['enriched'])->toBeTrue()
                ->and($result['word_count'])->toBe(2);
        });
        
        it('handles early stage failures gracefully', function () {
            $stage1 = fn($data) => Eff::succeed(['stage1' => $data]);
            $stage2 = fn($data) => Eff::fail(new \RuntimeException('Stage 2 failed'));
            $stage3 = fn($data) => Eff::succeed(['stage3' => $data]);
            
            $pipeline = fn($input) =>
                $stage1($input)
                    ->flatMap($stage2)
                    ->flatMap($stage3);
            
            $result = Run::syncResult($pipeline('test'));
            
            expect($result->isFailure())->toBeTrue();
            $error = $result->getErrorOrNull();
            expect($error->getMessage())->toBe('Stage 2 failed');
        });
    });
    
    describe('conditional processing branches', function () {
        it('routes data through different processing paths', function () {
            $classifyData = fn($data) => Eff::sync(function() use ($data) {
                $type = is_numeric($data['value']) ? 'number' : 'text';
                return [...$data, 'type' => $type];
            });
            
            $processNumber = fn($data) => Eff::sync(function() use ($data) {
                return [
                    ...$data,
                    'processed' => (float)$data['value'] * 2,
                    'processor' => 'number'
                ];
            });
            
            $processText = fn($data) => Eff::sync(function() use ($data) {
                return [
                    ...$data,
                    'processed' => strtoupper($data['value']),
                    'processor' => 'text'
                ];
            });
            
            $conditionalPipeline = fn($input) =>
                $classifyData($input)
                    ->flatMap(function($classified) use ($processNumber, $processText) {
                        return $classified['type'] === 'number'
                            ? $processNumber($classified)
                            : $processText($classified);
                    });
            
            $numberResult = Run::sync($conditionalPipeline(['value' => '42']));
            $textResult = Run::sync($conditionalPipeline(['value' => 'hello']));
            
            expect($numberResult['processed'])->toBe(84.0)
                ->and($numberResult['processor'])->toBe('number');
            
            expect($textResult['processed'])->toBe('HELLO')
                ->and($textResult['processor'])->toBe('text');
        });
        
        it('handles optional processing stages with Option types', function () {
            $processData = fn($data) => Eff::succeed($data);
            
            $optionalEnrichment = fn($data) => 
                isset($data['enrich']) && $data['enrich']
                    ? Option::some(['enriched_data' => 'additional_info'])
                    : Option::none();
            
            $pipeline = fn($input) =>
                $processData($input)
                    ->map(function($data) use ($optionalEnrichment) {
                        return $optionalEnrichment($data)
                            ->map(fn($enrichment) => [...$data, ...$enrichment])
                            ->whenNone($data);
                    });
            
            $enrichedResult = Run::sync($pipeline(['data' => 'test', 'enrich' => true]));
            $plainResult = Run::sync($pipeline(['data' => 'test', 'enrich' => false]));
            
            expect($enrichedResult['enriched_data'])->toBe('additional_info');
            expect(isset($plainResult['enriched_data']))->toBeFalse();
        });
    });
    
    describe('parallel processing stages', function () {
        it('processes multiple independent validations in parallel', function () {
            $validateEmail = fn($data) => 
                filter_var($data['email'], FILTER_VALIDATE_EMAIL)
                    ? Eff::succeed(['email_valid' => true])
                    : Eff::fail(new \InvalidArgumentException('Invalid email'));
            
            $validateAge = fn($data) => 
                is_numeric($data['age']) && $data['age'] >= 18
                    ? Eff::succeed(['age_valid' => true])
                    : Eff::fail(new \InvalidArgumentException('Invalid age'));
            
            $validateName = fn($data) => 
                !empty($data['name']) && strlen($data['name']) >= 2
                    ? Eff::succeed(['name_valid' => true])
                    : Eff::fail(new \InvalidArgumentException('Invalid name'));
            
            $parallelValidation = fn($userData) => Eff::allInParallel([
                $validateEmail($userData),
                $validateAge($userData),
                $validateName($userData)
            ])->map(fn($results) => array_merge($userData, ...$results));
            
            $validUser = [
                'email' => 'test@example.com',
                'age' => 25,
                'name' => 'John Doe'
            ];
            
            $result = Run::sync($parallelValidation($validUser));
            
            expect($result['email_valid'])->toBeTrue()
                ->and($result['age_valid'])->toBeTrue()
                ->and($result['name_valid'])->toBeTrue()
                ->and($result['email'])->toBe('test@example.com');
        });
        
        it('handles mixed parallel and sequential processing', function () {
            // Parallel data enrichment followed by sequential aggregation
            $fetchUserProfile = fn($userId) => Eff::sync(fn() => 
                ['profile' => ['name' => "User $userId", 'role' => 'user']]
            );
            
            $fetchUserPreferences = fn($userId) => Eff::sync(fn() => 
                ['preferences' => ['theme' => 'dark', 'language' => 'en']]
            );
            
            $fetchUserStats = fn($userId) => Eff::sync(fn() => 
                ['stats' => ['login_count' => 42, 'last_login' => '2024-01-01']]
            );
            
            $aggregateData = fn($parallelResults) => Eff::sync(function() use ($parallelResults) {
                return [
                    'user' => array_merge(...$parallelResults),
                    'aggregated_at' => time()
                ];
            });
            
            $userDataPipeline = fn($userId) => Eff::allInParallel([
                $fetchUserProfile($userId),
                $fetchUserPreferences($userId),
                $fetchUserStats($userId)
            ])->flatMap($aggregateData);
            
            $result = Run::sync($userDataPipeline(123));
            
            expect($result['user']['profile']['name'])->toBe('User 123')
                ->and($result['user']['preferences']['theme'])->toBe('dark')
                ->and($result['user']['stats']['login_count'])->toBe(42)
                ->and(isset($result['aggregated_at']))->toBeTrue();
        });
    });
    
    describe('library developer scenarios', function () {
        it('implements InstructorPHP structured data extraction pipeline', function () {
            // Simulate complete InstructorPHP extraction workflow
            $validatePrompt = fn($input) => 
                empty($input['prompt']) 
                    ? Eff::fail(new \InvalidArgumentException('Prompt is required'))
                    : Eff::succeed($input);
            
            $callLLMApi = fn($input) => Eff::sync(function() use ($input) {
                // Simulate LLM API response
                return [
                    ...$input,
                    'llm_response' => [
                        'choices' => [
                            ['message' => ['content' => '{"name": "John Doe", "age": 30, "skills": ["PHP", "Python"]}']]
                        ]
                    ]
                ];
            });
            
            $extractContent = fn($data) => Eff::sync(function() use ($data) {
                $content = $data['llm_response']['choices'][0]['message']['content'];
                return [...$data, 'raw_content' => $content];
            });
            
            $parseJson = fn($data) => Eff::sync(function() use ($data) {
                $parsed = json_decode($data['raw_content'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON from LLM: ' . json_last_error_msg());
                }
                return [...$data, 'parsed_data' => $parsed];
            });
            
            $validateSchema = fn($data) => Eff::sync(function() use ($data) {
                $required = ['name', 'age', 'skills'];
                $parsed = $data['parsed_data'];
                
                foreach ($required as $field) {
                    if (!isset($parsed[$field])) {
                        throw new \RuntimeException("Missing required field: $field");
                    }
                }
                
                if (!is_array($parsed['skills'])) {
                    throw new \RuntimeException('Skills must be an array');
                }
                
                return $data;
            });
            
            $transformOutput = fn($data) => Eff::sync(function() use ($data) {
                return [
                    'extraction_successful' => true,
                    'data' => $data['parsed_data'],
                    'metadata' => [
                        'prompt' => $data['prompt'],
                        'raw_response_length' => strlen($data['raw_content'])
                    ]
                ];
            });
            
            $instructorPipeline = fn($input) =>
                $validatePrompt($input)
                    ->flatMap($callLLMApi)
                    ->flatMap($extractContent)
                    ->flatMap($parseJson)
                    ->flatMap($validateSchema)
                    ->flatMap($transformOutput);
            
            $result = Run::sync($instructorPipeline([
                'prompt' => 'Extract person information from this text'
            ]));
            
            expect($result['extraction_successful'])->toBeTrue()
                ->and($result['data']['name'])->toBe('John Doe')
                ->and($result['data']['age'])->toBe(30)
                ->and($result['data']['skills'])->toBe(['PHP', 'Python'])
                ->and($result['metadata']['prompt'])->toContain('Extract person information');
        });
        
        it('implements PolyglotPHP multi-provider processing pipeline', function () {
            // Simulate multi-provider LLM pipeline with fallbacks
            $providers = [
                'openai' => ['endpoint' => 'https://api.openai.com', 'available' => true],
                'anthropic' => ['endpoint' => 'https://api.anthropic.com', 'available' => true],
                'local' => ['endpoint' => 'http://localhost:8000', 'available' => false]
            ];
            
            $selectProvider = fn($input) => Eff::sync(function() use ($input, $providers) {
                $preferred = $input['preferred_provider'] ?? 'openai';
                
                if (isset($providers[$preferred]) && $providers[$preferred]['available']) {
                    return [...$input, 'selected_provider' => $preferred];
                }
                
                // Fallback to first available provider
                foreach ($providers as $name => $config) {
                    if ($config['available']) {
                        return [...$input, 'selected_provider' => $name];
                    }
                }
                
                throw new \RuntimeException('No providers available');
            });
            
            $prepareRequest = fn($data) => Eff::sync(function() use ($data, $providers) {
                $provider = $data['selected_provider'];
                $config = $providers[$provider];
                
                return [
                    ...$data,
                    'request' => [
                        'url' => $config['endpoint'] . '/v1/completions',
                        'payload' => [
                            'prompt' => $data['prompt'],
                            'max_tokens' => $data['max_tokens'] ?? 100
                        ]
                    ]
                ];
            });
            
            $callProvider = fn($data) => Eff::sync(function() use ($data) {
                // Simulate provider-specific response
                $provider = $data['selected_provider'];
                return [
                    ...$data,
                    'response' => [
                        'provider' => $provider,
                        'content' => "Response from $provider: {$data['prompt']}",
                        'usage' => ['tokens' => 50]
                    ]
                ];
            });
            
            $normalizeResponse = fn($data) => Eff::sync(function() use ($data) {
                return [
                    'content' => $data['response']['content'],
                    'provider_used' => $data['response']['provider'],
                    'token_usage' => $data['response']['usage']['tokens'],
                    'request_metadata' => [
                        'original_prompt' => $data['prompt'],
                        'selected_provider' => $data['selected_provider']
                    ]
                ];
            });
            
            $polyglotPipeline = fn($input) =>
                $selectProvider($input)
                    ->flatMap($prepareRequest)
                    ->flatMap($callProvider)
                    ->flatMap($normalizeResponse);
            
            // Test with preferred provider available
            $result1 = Run::sync($polyglotPipeline([
                'prompt' => 'Hello, world!',
                'preferred_provider' => 'openai'
            ]));
            
            expect($result1['provider_used'])->toBe('openai')
                ->and($result1['content'])->toContain('Response from openai');
            
            // Test with fallback (preferred unavailable)
            $result2 = Run::sync($polyglotPipeline([
                'prompt' => 'Hello, world!',
                'preferred_provider' => 'local' // Not available
            ]));
            
            expect($result2['provider_used'])->toBe('openai') // Falls back to first available
                ->and($result2['request_metadata']['selected_provider'])->toBe('openai');
        });
        
        it('implements streaming data processing pipeline', function () {
            // Simulate streaming response processing
            $chunks = [
                '{"partial": "Hello", "status": "streaming", "complete": false}',
                '{"partial": " World!", "status": "streaming", "complete": false}',
                '{"partial": "", "status": "complete", "complete": true}'
            ];
            
            $processChunk = fn($chunk) => Eff::sync(function() use ($chunk) {
                $data = json_decode($chunk, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid chunk JSON');
                }
                return $data;
            });
            
            $aggregateChunks = fn($chunks) => Eff::sync(function() use ($chunks) {
                $content = '';
                $isComplete = false;
                
                foreach ($chunks as $chunk) {
                    $content .= $chunk['partial'];
                    if ($chunk['complete']) {
                        $isComplete = true;
                        break;
                    }
                }
                
                return [
                    'complete_content' => $content,
                    'is_complete' => $isComplete,
                    'chunk_count' => count($chunks)
                ];
            });
            
            $streamingPipeline = fn($rawChunks) => Eff::allInParallel(
                array_map($processChunk, $rawChunks)
            )->flatMap($aggregateChunks);
            
            $result = Run::sync($streamingPipeline($chunks));
            
            expect($result['complete_content'])->toBe('Hello World!')
                ->and($result['is_complete'])->toBeTrue()
                ->and($result['chunk_count'])->toBe(3);
        });
        
        it('implements error recovery and retry pipeline', function () {
            // Simulate resilient processing with multiple fallback strategies
            $attempt = 0;
            
            $unreliableOperation = function($data) use (&$attempt) {
                $attempt++;
                
                if ($attempt === 1) {
                    throw new \RuntimeException('Network timeout', 408);
                }
                if ($attempt === 2) {
                    throw new \RuntimeException('Rate limit exceeded', 429);
                }
                
                return [...$data, 'processed' => true, 'attempts' => $attempt];
            };
            
            $primaryProcessor = fn($data) => Eff::sync(fn() => $unreliableOperation($data));
            
            $fallbackProcessor = fn($data) => Eff::sync(function() use ($data) {
                return [...$data, 'processed' => true, 'fallback_used' => true];
            });
            
            $resilientPipeline = fn($input) =>
                $primaryProcessor($input)
                    ->catchError(
                        fn(\Throwable $e) => $e->getCode() === 408,
                        fn($e) => $primaryProcessor($input) // Retry timeout
                    )
                    ->catchError(
                        fn(\Throwable $e) => $e->getCode() === 429,
                        fn($e) => $primaryProcessor($input) // Retry rate limit
                    )
                    ->catchError(
                        \Throwable::class,
                        fn($e) => $fallbackProcessor($input) // Final fallback
                    );
            
            $result = Run::sync($resilientPipeline(['data' => 'test']));
            
            expect($result['processed'])->toBeTrue()
                ->and($result['attempts'])->toBe(3)
                ->and(isset($result['fallback_used']))->toBeFalse(); // Primary succeeded
        });
    });
});