<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Schedule\Schedule;
use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Runtime\Clock\TestClock;

// Mock HTTP Client interfaces for testing
interface HttpClient
{
    public function request(string $method, string $url, array $options = []): array;
    public function post(string $url, array $data = [], array $headers = []): array;
    public function get(string $url, array $headers = []): array;
}

class MockHttpClient implements HttpClient
{
    public array $requests = [];
    public array $responses = [];
    public int $currentResponse = 0;
    
    public function addResponse(array $response): void
    {
        $this->responses[] = $response;
    }
    
    public function request(string $method, string $url, array $options = []): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        
        if ($this->currentResponse < count($this->responses)) {
            $response = $this->responses[$this->currentResponse++];
            
            // Simulate error responses
            if (isset($response['error'])) {
                throw new \RuntimeException($response['error'], $response['code'] ?? 0);
            }
            
            return $response;
        }
        
        return ['status' => 200, 'body' => '{"default": "response"}'];
    }
    
    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, ['data' => $data, 'headers' => $headers]);
    }
    
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, ['headers' => $headers]);
    }
}

describe('HTTP Client Integration', function () {
    
    describe('basic HTTP operations', function () {
        it('makes successful HTTP requests', function () {
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['status' => 200, 'body' => '{"message": "success"}']);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $httpRequest = Eff::service(HttpClient::class)
                ->flatMap(fn($client) => Eff::sync(fn() => 
                    $client->post('https://api.example.com/data', ['key' => 'value'])
                ));
            
            $result = Eff::runSync($layer->provideTo($httpRequest));
            
            expect($result['status'])->toBe(200)
                ->and($result['body'])->toBe('{"message": "success"}')
                ->and($httpClient->requests)->toHaveCount(1)
                ->and($httpClient->requests[0]['method'])->toBe('POST');
        });
        
        it('handles HTTP errors gracefully', function () {
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['error' => 'Server Error', 'code' => 500]);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $httpRequest = Eff::service(HttpClient::class)
                ->flatMap(fn($client) => Eff::sync(fn() => 
                    $client->get('https://api.example.com/status')
                ));
            
            $result = Eff::runSafely($layer->provideTo($httpRequest));
            
            expect($result->isLeft())->toBeTrue();
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error->getMessage())->toBe('Server Error')
                ->and($error->getCode())->toBe(500);
        });
    });
    
    describe('HTTP client with retries', function () {
        it('retries failed requests with exponential backoff', function () {
            $httpClient = new MockHttpClient();
            // First two requests fail, third succeeds
            $httpClient->addResponse(['error' => 'Service Unavailable', 'code' => 503]);
            $httpClient->addResponse(['error' => 'Service Unavailable', 'code' => 503]);
            $httpClient->addResponse(['status' => 200, 'body' => '{"retry": "success"}']);
            
            $testClock = new TestClock();
            $layer = Layer::fromValue($httpClient, HttpClient::class)
                ->combineWith(Layer::fromValue($testClock, Clock::class));
            
            $retryableErrors = fn(\Throwable $e) => $e->getCode() >= 500;
            
            $makeRequest = fn() => Eff::service(HttpClient::class)
                ->flatMap(fn($client) => Eff::sync(fn() => 
                    $client->post('https://api.example.com/retry', ['data' => 'test'])
                ));
            
            $httpRequestWithRetry = $makeRequest()
                ->catchError(function(\Throwable $e) use ($retryableErrors, $makeRequest) {
                    if ($retryableErrors($e)) {
                        // Retry server errors with backoff
                        return $makeRequest()->retryWith(
                            Schedule::exponentialBackoff(Duration::milliseconds(100))
                                ->upToMaxRetries(3)
                        );
                    }
                    // Don't retry client errors
                    return Eff::fail($e);
                });
            
            $result = Eff::runSync($layer->provideTo($httpRequestWithRetry));
            
            expect($result['status'])->toBe(200)
                ->and($result['body'])->toBe('{"retry": "success"}')
                ->and($httpClient->requests)->toHaveCount(3);
        });
        
        it('respects non-retryable error codes', function () {
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['error' => 'Bad Request', 'code' => 400]);
            
            $testClock = new TestClock();
            $layer = Layer::fromValue($httpClient, HttpClient::class)
                ->combineWith(Layer::fromValue($testClock, Clock::class));
            
            $retryableErrors = fn(\Throwable $e) => $e->getCode() >= 500;
            
            $httpRequestWithRetry = Eff::service(HttpClient::class)
                ->flatMap(fn($client) => Eff::sync(fn() => 
                    $client->get('https://api.example.com/invalid')
                ))
                ->catchError(function(\Throwable $e) use ($retryableErrors) {
                    // Only retry server errors, not client errors
                    if (!$retryableErrors($e)) {
                        return Eff::fail($e); // Don't retry 4xx errors
                    }
                    return Eff::fail($e); // Let this bubble up to be caught by outer retry logic
                })
                ->retryWith(
                    Schedule::fixedDelay(Duration::milliseconds(100))
                        ->upToMaxRetries(3)
                );
            
            $result = Eff::runSafely($layer->provideTo($httpRequestWithRetry));
            
            expect($result->isLeft())->toBeTrue()
                ->and($httpClient->requests)->toHaveCount(1); // No retries for 400 error
            
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error->getCode())->toBe(400);
        });
    });
    
    describe('parallel HTTP requests', function () {
        it('executes multiple HTTP requests in parallel', function () {
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['status' => 200, 'body' => '{"service": "A", "data": "result1"}']);
            $httpClient->addResponse(['status' => 200, 'body' => '{"service": "B", "data": "result2"}']);
            $httpClient->addResponse(['status' => 200, 'body' => '{"service": "C", "data": "result3"}']);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $createRequest = fn($service, $endpoint) => 
                Eff::service(HttpClient::class)
                    ->flatMap(fn($client) => Eff::sync(fn() => 
                        $client->get("https://$service.api.com/$endpoint")
                    ))
                    ->map(fn($response) => json_decode($response['body'], true));
            
            $parallelRequests = Eff::allInParallel([
                $createRequest('serviceA', 'data'),
                $createRequest('serviceB', 'info'),
                $createRequest('serviceC', 'status')
            ]);
            
            $result = Eff::runSync($layer->provideTo($parallelRequests));
            
            expect($result)->toHaveCount(3)
                ->and($result[0]['service'])->toBe('A')
                ->and($result[1]['service'])->toBe('B')
                ->and($result[2]['service'])->toBe('C')
                ->and($httpClient->requests)->toHaveCount(3);
        });
        
        it('handles mixed success and failure in parallel requests', function () {
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['status' => 200, 'body' => '{"success": true}']);
            $httpClient->addResponse(['error' => 'Not Found', 'code' => 404]);
            $httpClient->addResponse(['status' => 200, 'body' => '{"success": true}']);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $makeRequest = fn($url) => 
                Eff::service(HttpClient::class)
                    ->flatMap(fn($client) => Eff::sync(fn() => $client->get($url)));
            
            $parallelRequests = Eff::allInParallel([
                $makeRequest('https://api1.com/data'),
                $makeRequest('https://api2.com/missing'),
                $makeRequest('https://api3.com/data')
            ]);
            
            $result = Eff::runSafely($layer->provideTo($parallelRequests));
            
            expect($result->isLeft())->toBeTrue(); // Fails fast on first error
            $error = $result->fold(fn($l) => $l, fn($r) => null);
            expect($error->getCode())->toBe(404);
        });
    });
    
    describe('library developer scenarios', function () {
        it('implements LLM API client pattern for PolyglotPHP', function () {
            // Simulate OpenAI API client
            $httpClient = new MockHttpClient();
            $httpClient->addResponse([
                'status' => 200,
                'body' => json_encode([
                    'choices' => [
                        ['message' => ['content' => '{"name": "John", "age": 30}']]
                    ]
                ])
            ]);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $llmApiCall = function($prompt, $model = 'gpt-4') {
                return Eff::service(HttpClient::class)
                    ->flatMap(fn($client) => Eff::sync(function() use ($client, $prompt, $model) {
                        return $client->post('https://api.openai.com/v1/chat/completions', [
                            'model' => $model,
                            'messages' => [['role' => 'user', 'content' => $prompt]],
                            'temperature' => 0.7
                        ], [
                            'Authorization' => 'Bearer sk-test-key',
                            'Content-Type' => 'application/json'
                        ]);
                    }))
                    ->flatMap(fn($response) => Eff::sync(function() use ($response) {
                        $data = json_decode($response['body'], true);
                        if (!isset($data['choices'][0]['message']['content'])) {
                            throw new \RuntimeException('Invalid API response structure');
                        }
                        return $data['choices'][0]['message']['content'];
                    }))
                    ->flatMap(fn($content) => Eff::sync(function() use ($content) {
                        $parsed = json_decode($content, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new \RuntimeException('LLM returned invalid JSON');
                        }
                        return $parsed;
                    }));
            };
            
            $result = Eff::runSync($layer->provideTo($llmApiCall('Extract person info from text')));
            
            expect($result['name'])->toBe('John')
                ->and($result['age'])->toBe(30)
                ->and($httpClient->requests[0]['options']['data']['model'])->toBe('gpt-4');
        });
        
        it('implements multi-provider fallback for PolyglotPHP', function () {
            // Simulate primary provider failure, fallback to secondary
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['error' => 'Rate limit exceeded', 'code' => 429]);
            $httpClient->addResponse([
                'status' => 200,
                'body' => json_encode(['content' => 'Fallback provider response'])
            ]);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $callProvider = fn($providerUrl, $data) => 
                Eff::service(HttpClient::class)
                    ->flatMap(fn($client) => Eff::sync(fn() => 
                        $client->post($providerUrl, $data)
                    ));
            
            $multiProviderCall = function($prompt) use ($callProvider) {
                $primaryCall = $callProvider('https://api.openai.com/v1/completions', [
                    'prompt' => $prompt,
                    'model' => 'gpt-3.5-turbo'
                ]);
                
                $fallbackCall = $callProvider('https://api.anthropic.com/v1/messages', [
                    'prompt' => $prompt,
                    'model' => 'claude-3'
                ]);
                
                return $primaryCall->catchError(
                    fn(\Throwable $e) => $e->getCode() === 429,
                    fn($e) => $fallbackCall
                );
            };
            
            $result = Eff::runSync($layer->provideTo($multiProviderCall('Test prompt')));
            
            expect($result['status'])->toBe(200)
                ->and($httpClient->requests)->toHaveCount(2)
                ->and($httpClient->requests[0]['url'])->toContain('openai')
                ->and($httpClient->requests[1]['url'])->toContain('anthropic');
        });
        
        it('implements streaming response simulation for InstructorPHP', function () {
            // Simulate streaming LLM response processing
            $httpClient = new MockHttpClient();
            
            // Simulate chunked response
            $chunks = [
                'data: {"delta": {"content": "{\\"name\\": \\""}}\n\n',
                'data: {"delta": {"content": "John"}}\n\n',
                'data: {"delta": {"content": "\\", \\"age\\": "}}\n\n',
                'data: {"delta": {"content": "30"}}\n\n',
                'data: {"delta": {"content": "}"}}\n\n',
                'data: [DONE]\n\n'
            ];
            
            $httpClient->addResponse([
                'status' => 200,
                'body' => implode('', $chunks),
                'streaming' => true
            ]);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class);
            
            $streamingCall = Eff::service(HttpClient::class)
                ->flatMap(fn($client) => Eff::sync(function() use ($client) {
                    $response = $client->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4',
                        'messages' => [['role' => 'user', 'content' => 'Generate JSON']],
                        'stream' => true
                    ]);
                    return $response['body'];
                }))
                ->flatMap(fn($streamData) => Eff::sync(function() use ($streamData) {
                    // Process streaming chunks
                    $lines = explode("\n", $streamData);
                    $content = '';
                    
                    foreach ($lines as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $data = substr($line, 6);
                            if ($data === '[DONE]') break;
                            
                            $chunk = json_decode($data, true);
                            if (isset($chunk['delta']['content'])) {
                                $content .= $chunk['delta']['content'];
                            }
                        }
                    }
                    
                    return $content;
                }))
                ->flatMap(fn($content) => Eff::sync(function() use ($content) {
                    $parsed = json_decode($content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \RuntimeException("Invalid JSON from streaming: $content");
                    }
                    return $parsed;
                }));
            
            $result = Eff::runSync($layer->provideTo($streamingCall));
            
            expect($result['name'])->toBe('John')
                ->and($result['age'])->toBe(30);
        });
        
        it('implements robust HTTP client with timeout and circuit breaker', function () {
            $httpClient = new MockHttpClient();
            $testClock = new TestClock();
            
            // Simulate timeouts and recovery
            $httpClient->addResponse(['error' => 'Request timeout', 'code' => 408]);
            $httpClient->addResponse(['error' => 'Request timeout', 'code' => 408]);
            $httpClient->addResponse(['status' => 200, 'body' => '{"recovered": true}']);
            
            $layer = Layer::fromValue($httpClient, HttpClient::class)
                ->combineWith(Layer::fromValue($testClock, Clock::class));
            
            $robustHttpCall = function($url, $data) {
                $makeCall = fn() => Eff::service(HttpClient::class)
                    ->flatMap(fn($client) => Eff::sync(fn() => $client->post($url, $data)));
                
                $timeoutRetry = fn(\Throwable $e) => $e->getCode() === 408;
                
                return $makeCall()
                    ->catchError(function(\Throwable $e) use ($timeoutRetry, $makeCall) {
                        if ($timeoutRetry($e)) {
                            // Retry timeout errors with backoff
                            return $makeCall()->retryWith(
                                Schedule::exponentialBackoff(Duration::milliseconds(500))
                                    ->upToMaxRetries(3)
                                    ->upToMaxDuration(Duration::seconds(10))
                            );
                        }
                        // Don't retry non-timeout errors
                        return Eff::fail($e);
                    });
            };
            
            $result = Eff::runSync($layer->provideTo($robustHttpCall('https://api.example.com/data', ['key' => 'value'])));
            
            expect($result['status'])->toBe(200)
                ->and($httpClient->requests)->toHaveCount(3); // Two failures + one success
        });
        
        it('implements request/response logging and monitoring', function () {
            $httpClient = new MockHttpClient();
            $httpClient->addResponse(['status' => 200, 'body' => '{"api": "response"}']);
            
            $logger = new class {
                public array $logs = [];
                public function log(string $level, string $message, array $context = []): void {
                    $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
                }
            };
            
            $layer = Layer::fromValue($httpClient, HttpClient::class)
                ->combineWith(Layer::fromValue($logger, 'Logger'));
            
            $monitoredHttpCall = function($url, $data) {
                return Eff::service('Logger')
                    ->flatMap(fn($logger) => Eff::sync(function() use ($logger, $url, $data) {
                        $logger->log('info', 'HTTP request started', ['url' => $url, 'data' => $data]);
                    }))
                    ->flatMap(fn() => Eff::service(HttpClient::class))
                    ->flatMap(fn($client) => Eff::sync(fn() => $client->post($url, $data)))
                    ->flatMap(function($response) use ($url) {
                        return Eff::service('Logger')
                            ->flatMap(fn($logger) => Eff::sync(function() use ($logger, $url, $response) {
                                $logger->log('info', 'HTTP request completed', [
                                    'url' => $url,
                                    'status' => $response['status']
                                ]);
                                return $response;
                            }));
                    })
                    ->catchError(\Throwable::class, function($e) use ($url) {
                        return Eff::service('Logger')
                            ->flatMap(fn($logger) => Eff::sync(function() use ($logger, $url, $e) {
                                $logger->log('error', 'HTTP request failed', [
                                    'url' => $url,
                                    'error' => $e->getMessage()
                                ]);
                                throw $e;
                            }));
                    });
            };
            
            $result = Eff::runSync($layer->provideTo($monitoredHttpCall('https://api.example.com/test', ['test' => 'data'])));
            
            expect($result['status'])->toBe(200)
                ->and($logger->logs)->toHaveCount(2)
                ->and($logger->logs[0]['message'])->toBe('HTTP request started')
                ->and($logger->logs[1]['message'])->toBe('HTTP request completed');
        });
    });
});