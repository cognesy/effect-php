<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Layer\Layer;

// Configuration interfaces for testing
interface Config
{
    public function get(string $key): mixed;
    public function has(string $key): bool;
    public function set(string $key, mixed $value): void;
}

interface DatabaseConfig
{
    public function getHost(): string;
    public function getPort(): int;
    public function getDatabase(): string;
    public function getCredentials(): array;
}

interface ApiConfig
{
    public function getBaseUrl(): string;
    public function getApiKey(): string;
    public function getTimeout(): int;
    public function getRetryAttempts(): int;
}

// Mock implementations
class ArrayConfig implements Config
{
    public function __construct(private array $data = []) {}
    
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
    
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
    
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}

class MockDatabaseConfig implements DatabaseConfig
{
    public function __construct(
        private string $host = 'localhost',
        private int $port = 5432,
        private string $database = 'test_db',
        private array $credentials = ['user' => 'test', 'password' => 'secret']
    ) {}
    
    public function getHost(): string { return $this->host; }
    public function getPort(): int { return $this->port; }
    public function getDatabase(): string { return $this->database; }
    public function getCredentials(): array { return $this->credentials; }
}

class MockApiConfig implements ApiConfig
{
    public function __construct(
        private string $baseUrl = 'https://api.example.com',
        private string $apiKey = 'test-key',
        private int $timeout = 30,
        private int $retryAttempts = 3
    ) {}
    
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function getApiKey(): string { return $this->apiKey; }
    public function getTimeout(): int { return $this->timeout; }
    public function getRetryAttempts(): int { return $this->retryAttempts; }
}

describe('Configuration Management', function () {
    
    describe('basic configuration access', function () {
        it('provides configuration services through context', function () {
            $config = new ArrayConfig([
                'app_name' => 'Test App',
                'debug' => true,
                'cache_ttl' => 3600
            ]);
            
            $layer = Layer::fromValue($config, Config::class);
            
            $configEffect = Eff::service(Config::class)
                ->map(fn($config) => [
                    'name' => $config->get('app_name'),
                    'debug' => $config->get('debug'),
                    'ttl' => $config->get('cache_ttl')
                ]);
            
            $result = Run::sync($layer->provideTo($configEffect));
            
            expect($result['name'])->toBe('Test App')
                ->and($result['debug'])->toBeTrue()
                ->and($result['ttl'])->toBe(3600);
        });
        
        it('handles missing configuration values gracefully', function () {
            $config = new ArrayConfig(['existing_key' => 'value']);
            $layer = Layer::fromValue($config, Config::class);
            
            $configEffect = Eff::service(Config::class)
                ->map(fn($config) => [
                    'existing' => $config->get('existing_key'),
                    'missing' => $config->get('missing_key') ?? 'default_value',
                    'has_existing' => $config->has('existing_key'),
                    'has_missing' => $config->has('missing_key')
                ]);
            
            $result = Run::sync($layer->provideTo($configEffect));
            
            expect($result['existing'])->toBe('value')
                ->and($result['missing'])->toBe('default_value')
                ->and($result['has_existing'])->toBeTrue()
                ->and($result['has_missing'])->toBeFalse();
        });
    });
    
    describe('layered configuration composition', function () {
        it('combines multiple configuration sources', function () {
            $dbConfig = new MockDatabaseConfig();
            $apiConfig = new MockApiConfig();
            $appConfig = new ArrayConfig(['environment' => 'test']);
            
            $configLayer = Layer::fromValue($dbConfig, DatabaseConfig::class)
                ->combineWith(Layer::fromValue($apiConfig, ApiConfig::class))
                ->combineWith(Layer::fromValue($appConfig, Config::class));
            
            $compositeConfigEffect = Eff::service(DatabaseConfig::class)
                ->flatMap(fn($db) => 
                    Eff::service(ApiConfig::class)
                        ->flatMap(fn($api) =>
                            Eff::service(Config::class)
                                ->map(fn($app) => [
                                    'database' => [
                                        'host' => $db->getHost(),
                                        'port' => $db->getPort(),
                                        'name' => $db->getDatabase()
                                    ],
                                    'api' => [
                                        'url' => $api->getBaseUrl(),
                                        'timeout' => $api->getTimeout()
                                    ],
                                    'app' => [
                                        'environment' => $app->get('environment')
                                    ]
                                ])
                        )
                );
            
            $result = Run::sync($configLayer->provideTo($compositeConfigEffect));
            
            expect($result['database']['host'])->toBe('localhost')
                ->and($result['database']['port'])->toBe(5432)
                ->and($result['api']['url'])->toBe('https://api.example.com')
                ->and($result['api']['timeout'])->toBe(30)
                ->and($result['app']['environment'])->toBe('test');
        });
        
        it('supports environment-specific configuration layers', function () {
            $defaultConfig = new ArrayConfig([
                'debug' => false,
                'cache_ttl' => 3600,
                'log_level' => 'info'
            ]);
            
            $testConfig = new ArrayConfig([
                'debug' => true,
                'cache_ttl' => 60,
                'test_mode' => true
            ]);
            
            // Test environment overrides defaults
            $testLayer = Layer::fromValue($defaultConfig, 'DefaultConfig')
                ->combineWith(Layer::fromValue($testConfig, 'TestConfig'));
            
            $environmentConfigEffect = Eff::service('DefaultConfig')
                ->flatMap(fn($default) =>
                    Eff::service('TestConfig')
                        ->map(fn($test) => [
                            'debug' => $test->get('debug') ?? $default->get('debug'),
                            'cache_ttl' => $test->get('cache_ttl') ?? $default->get('cache_ttl'),
                            'log_level' => $test->get('log_level') ?? $default->get('log_level'),
                            'test_mode' => $test->get('test_mode') ?? false
                        ])
                );
            
            $result = Run::sync($testLayer->provideTo($environmentConfigEffect));
            
            expect($result['debug'])->toBeTrue() // Overridden
                ->and($result['cache_ttl'])->toBe(60) // Overridden
                ->and($result['log_level'])->toBe('info') // From default
                ->and($result['test_mode'])->toBeTrue(); // Test-specific
        });
    });
    
    describe('dynamic configuration loading', function () {
        it('loads configuration from effects', function () {
            $configLoader = function() {
                return Eff::sync(function() {
                    // Simulate loading from file, environment, etc.
                    return new ArrayConfig([
                        'loaded_at' => time(),
                        'source' => 'dynamic',
                        'features' => ['feature_a', 'feature_b']
                    ]);
                });
            };
            
            $dynamicConfigLayer = Layer::fromEffect($configLoader(), Config::class);
            
            $configEffect = Eff::service(Config::class)
                ->map(fn($config) => [
                    'source' => $config->get('source'),
                    'features' => $config->get('features'),
                    'has_timestamp' => $config->has('loaded_at')
                ]);
            
            $result = Run::sync($dynamicConfigLayer->provideTo($configEffect));
            
            expect($result['source'])->toBe('dynamic')
                ->and($result['features'])->toBe(['feature_a', 'feature_b'])
                ->and($result['has_timestamp'])->toBeTrue();
        });
        
        it('handles configuration loading failures', function () {
            $failingConfigLoader = function() {
                return Eff::sync(function() {
                    throw new \RuntimeException('Configuration file not found');
                });
            };
            
            $fallbackConfig = new ArrayConfig(['fallback' => true]);
            
            $resilientConfigEffect = $failingConfigLoader()
                ->catchError(\RuntimeException::class, fn($e) => 
                    Eff::succeed($fallbackConfig)
                );
            
            $resilientConfigLayer = Layer::fromEffect($resilientConfigEffect, Config::class);
            
            $configEffect = Eff::service(Config::class)
                ->map(fn($config) => $config->get('fallback'));
            
            $result = Run::sync($resilientConfigLayer->provideTo($configEffect));
            
            expect($result)->toBeTrue();
        });
    });
    
    describe('configuration validation', function () {
        it('validates required configuration values', function () {
            $config = new ArrayConfig([
                'api_key' => 'valid-key',
                'timeout' => 30
                // Missing 'base_url'
            ]);
            
            $validateConfig = fn($config) => Eff::sync(function() use ($config) {
                $required = ['api_key', 'base_url', 'timeout'];
                $missing = [];
                
                foreach ($required as $key) {
                    if (!$config->has($key)) {
                        $missing[] = $key;
                    }
                }
                
                if (!empty($missing)) {
                    throw new \InvalidArgumentException(
                        'Missing required configuration: ' . implode(', ', $missing)
                    );
                }
                
                return $config;
            });
            
            $layer = Layer::fromValue($config, Config::class);
            
            $validationEffect = Eff::service(Config::class)
                ->flatMap($validateConfig);
            
            $result = Run::syncResult($layer->provideTo($validationEffect));
            
            expect($result->isFailure())->toBeTrue();
            $error = $result->getErrorOrNull();
            expect($error->getMessage())->toContain('Missing required configuration: base_url');
        });
        
        it('validates configuration value types and ranges', function () {
            $config = new ArrayConfig([
                'port' => '3000', // String instead of int
                'timeout' => -5,   // Invalid negative value
                'debug' => 'yes'   // String instead of bool
            ]);
            
            $validateAndNormalize = fn($config) => Eff::sync(function() use ($config) {
                $normalized = new ArrayConfig();
                
                // Validate and normalize port
                $port = $config->get('port');
                if (!is_numeric($port) || $port < 1 || $port > 65535) {
                    throw new \InvalidArgumentException('Port must be a number between 1 and 65535');
                }
                $normalized->set('port', (int)$port);
                
                // Validate and normalize timeout
                $timeout = $config->get('timeout');
                if (!is_numeric($timeout) || $timeout < 0) {
                    throw new \InvalidArgumentException('Timeout must be a positive number');
                }
                $normalized->set('timeout', (int)$timeout);
                
                // Validate and normalize debug
                $debug = $config->get('debug');
                if (is_string($debug)) {
                    $debug = in_array(strtolower($debug), ['true', 'yes', '1']);
                }
                $normalized->set('debug', (bool)$debug);
                
                return $normalized;
            });
            
            $layer = Layer::fromValue($config, Config::class);
            
            $validationEffect = Eff::service(Config::class)
                ->flatMap($validateAndNormalize);
            
            $result = Run::syncResult($layer->provideTo($validationEffect));
            
            expect($result->isFailure())->toBeTrue();
            $error = $result->getErrorOrNull();
            expect($error->getMessage())->toContain('Timeout must be a positive number');
        });
    });
    
    describe('library developer scenarios', function () {
        it('configures InstructorPHP with multiple LLM providers', function () {
            // Simulate InstructorPHP configuration
            $instructorConfig = new ArrayConfig([
                'default_provider' => 'openai',
                'providers' => [
                    'openai' => [
                        'api_key' => 'sk-test-openai',
                        'base_url' => 'https://api.openai.com/v1',
                        'models' => ['gpt-3.5-turbo', 'gpt-4']
                    ],
                    'anthropic' => [
                        'api_key' => 'sk-test-anthropic',
                        'base_url' => 'https://api.anthropic.com/v1',
                        'models' => ['claude-3-sonnet', 'claude-3-opus']
                    ]
                ],
                'extraction' => [
                    'max_retries' => 3,
                    'timeout' => 30,
                    'validate_schema' => true
                ]
            ]);
            
            $layer = Layer::fromValue($instructorConfig, Config::class);
            
            $setupInstructorEffect = Eff::service(Config::class)
                ->map(function($config) {
                    $defaultProvider = $config->get('default_provider');
                    $providers = $config->get('providers');
                    $extraction = $config->get('extraction');
                    
                    return [
                        'active_provider' => $defaultProvider,
                        'provider_config' => $providers[$defaultProvider],
                        'extraction_settings' => $extraction,
                        'available_models' => $providers[$defaultProvider]['models']
                    ];
                });
            
            $result = Run::sync($layer->provideTo($setupInstructorEffect));
            
            expect($result['active_provider'])->toBe('openai')
                ->and($result['provider_config']['api_key'])->toBe('sk-test-openai')
                ->and($result['extraction_settings']['max_retries'])->toBe(3)
                ->and($result['available_models'])->toContain('gpt-4');
        });
        
        it('configures PolyglotPHP with environment-specific settings', function () {
            // Production configuration
            $prodConfig = new ArrayConfig([
                'environment' => 'production',
                'debug' => false,
                'cache_enabled' => true,
                'rate_limiting' => [
                    'requests_per_minute' => 100,
                    'burst_limit' => 10
                ],
                'providers' => [
                    'openai' => ['priority' => 1, 'fallback' => true],
                    'anthropic' => ['priority' => 2, 'fallback' => true]
                ]
            ]);
            
            // Development configuration overrides
            $devConfig = new ArrayConfig([
                'debug' => true,
                'cache_enabled' => false,
                'rate_limiting' => [
                    'requests_per_minute' => 1000,
                    'burst_limit' => 50
                ]
            ]);
            
            // Test which environment to use based on some condition
            $useDevMode = true;
            
            $configLayer = $useDevMode 
                ? Layer::fromValue($prodConfig, 'ProdConfig')
                    ->combineWith(Layer::fromValue($devConfig, 'DevConfig'))
                : Layer::fromValue($prodConfig, 'ProdConfig');
            
            $polyglotConfigEffect = function() use ($useDevMode) {
                if ($useDevMode) {
                    return Eff::service('ProdConfig')
                        ->flatMap(fn($prod) =>
                            Eff::service('DevConfig')
                                ->map(fn($dev) => [
                                    'environment' => $prod->get('environment'),
                                    'debug' => $dev->get('debug') ?? $prod->get('debug'),
                                    'cache_enabled' => $dev->get('cache_enabled') ?? $prod->get('cache_enabled'),
                                    'rate_limiting' => array_merge(
                                        $prod->get('rate_limiting'),
                                        $dev->get('rate_limiting') ?? []
                                    ),
                                    'providers' => $prod->get('providers')
                                ])
                        );
                } else {
                    return Eff::service('ProdConfig')
                        ->map(fn($prod) => [
                            'environment' => $prod->get('environment'),
                            'debug' => $prod->get('debug'),
                            'cache_enabled' => $prod->get('cache_enabled'),
                            'rate_limiting' => $prod->get('rate_limiting'),
                            'providers' => $prod->get('providers')
                        ]);
                }
            };
            
            $result = Run::sync($configLayer->provideTo($polyglotConfigEffect()));
            
            expect($result['environment'])->toBe('production')
                ->and($result['debug'])->toBeTrue() // Overridden for dev
                ->and($result['cache_enabled'])->toBeFalse() // Overridden for dev
                ->and($result['rate_limiting']['requests_per_minute'])->toBe(1000); // Dev override
        });
        
        it('manages secrets and sensitive configuration', function () {
            // Simulate secure configuration management
            $publicConfig = new ArrayConfig([
                'app_name' => 'MyApp',
                'version' => '1.0.0',
                'features' => ['feature_a', 'feature_b']
            ]);
            
            $secretsConfig = new ArrayConfig([
                'api_keys' => [
                    'openai' => 'sk-secret-openai-key',
                    'anthropic' => 'sk-secret-anthropic-key'
                ],
                'database' => [
                    'password' => 'super-secret-password'
                ]
            ]);
            
            $secureConfigLayer = Layer::fromValue($publicConfig, 'PublicConfig')
                ->combineWith(Layer::fromValue($secretsConfig, 'SecretsConfig'));
            
            $secureSetupEffect = Eff::service('PublicConfig')
                ->flatMap(fn($public) =>
                    Eff::service('SecretsConfig')
                        ->map(fn($secrets) => [
                            'public_info' => [
                                'name' => $public->get('app_name'),
                                'version' => $public->get('version'),
                                'features' => $public->get('features')
                            ],
                            'secure_config' => [
                                'has_openai_key' => !empty($secrets->get('api_keys')['openai']),
                                'has_db_password' => !empty($secrets->get('database')['password']),
                                // Don't expose actual secrets in result
                                'openai_key_length' => strlen($secrets->get('api_keys')['openai'])
                            ]
                        ])
                );
            
            $result = Run::sync($secureConfigLayer->provideTo($secureSetupEffect));
            
            expect($result['public_info']['name'])->toBe('MyApp')
                ->and($result['secure_config']['has_openai_key'])->toBeTrue()
                ->and($result['secure_config']['has_db_password'])->toBeTrue()
                ->and($result['secure_config']['openai_key_length'])->toBe(20);
        });
        
        it('implements configuration hot-reloading simulation', function () {
            // Simulate configuration that can be reloaded
            $currentConfigVersion = 1;
            
            $configFactory = function() use (&$currentConfigVersion) {
                return Eff::sync(function() use (&$currentConfigVersion) {
                    return new ArrayConfig([
                        'version' => $currentConfigVersion,
                        'setting_a' => "value_v{$currentConfigVersion}",
                        'setting_b' => $currentConfigVersion * 10,
                        'last_updated' => time()
                    ]);
                });
            };
            
            $reloadableConfigLayer = Layer::fromEffect($configFactory(), Config::class);
            
            // Initial load
            $configEffect = Eff::service(Config::class)
                ->map(fn($config) => [
                    'version' => $config->get('version'),
                    'setting_a' => $config->get('setting_a'),
                    'setting_b' => $config->get('setting_b')
                ]);
            
            $result1 = Run::sync($reloadableConfigLayer->provideTo($configEffect));
            
            expect($result1['version'])->toBe(1)
                ->and($result1['setting_a'])->toBe('value_v1')
                ->and($result1['setting_b'])->toBe(10);
            
            // Simulate configuration change
            $currentConfigVersion = 2;
            $reloadedConfigLayer = Layer::fromEffect($configFactory(), Config::class);
            
            $result2 = Run::sync($reloadedConfigLayer->provideTo($configEffect));
            
            expect($result2['version'])->toBe(2)
                ->and($result2['setting_a'])->toBe('value_v2')
                ->and($result2['setting_b'])->toBe(20);
        });
    });
});