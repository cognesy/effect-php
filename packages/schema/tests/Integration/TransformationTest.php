<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;
use EffectPHP\Core\Eff;

describe('Bidirectional Transformation Integration', function () {
    
    it('transforms dates between string and DateTime objects', function () {
        $dateSchema = Schema::transform(
            Schema::string(), // Input format: ISO string
            Schema::string(), // Output format: formatted string
            function (string $isoString): string {
                $date = new DateTime($isoString);
                return $date->format('Y-m-d H:i:s');
            },
            function (string $formatted): string {
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $formatted);
                return $date->format('c'); // ISO format
            }
        );

        // Test decode: ISO string → formatted string
        $isoInput = '2024-01-15T10:30:00Z';
        $decodeResult = Eff::runSafely($dateSchema->decode($isoInput));
        
        expect($decodeResult->isRight())->toBeTrue();
        $formatted = $decodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($formatted)->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');

        // Test encode: formatted string → ISO string
        $encodeResult = Eff::runSafely($dateSchema->encode($formatted));
        
        expect($encodeResult->isRight())->toBeTrue();
        $isoOutput = $encodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($isoOutput)->toMatch('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('transforms between different data formats with validation', function () {
        // Transform between external API format and internal format
        $userTransform = Schema::transform(
            // External format (from API)
            Schema::object([
                'user_id' => Schema::number(),
                'full_name' => Schema::string(),
                'email_address' => Schema::string()->pipe(fn($s) => Schema::email($s)),
                'birth_year' => Schema::number(),
            ], ['user_id', 'full_name', 'email_address', 'birth_year']),
            
            // Internal format (for our system)
            Schema::object([
                'id' => Schema::number(),
                'name' => Schema::string(),
                'email' => Schema::string(),
                'age' => Schema::number(),
            ], ['id', 'name', 'email', 'age']),
            
            // Decode: external → internal
            function (array $external): array {
                $currentYear = (int) date('Y');
                return [
                    'id' => $external['user_id'],
                    'name' => $external['full_name'],
                    'email' => $external['email_address'],
                    'age' => $currentYear - $external['birth_year'],
                ];
            },
            
            // Encode: internal → external
            function (array $internal): array {
                $currentYear = (int) date('Y');
                return [
                    'user_id' => $internal['id'],
                    'full_name' => $internal['name'],
                    'email_address' => $internal['email'],
                    'birth_year' => $currentYear - $internal['age'],
                ];
            }
        );

        $externalData = [
            'user_id' => 123,
            'full_name' => 'John Doe',
            'email_address' => 'john@example.com',
            'birth_year' => 1990,
        ];

        // Test decode transformation
        $decodeResult = Eff::runSafely($userTransform->decode($externalData));
        expect($decodeResult->isRight())->toBeTrue();
        
        $internal = $decodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($internal['id'])->toBe(123);
        expect($internal['name'])->toBe('John Doe');
        expect($internal['email'])->toBe('john@example.com');
        expect($internal['age'])->toBe((int) date('Y') - 1990); // Current year - birth year

        // Test encode transformation
        $encodeResult = Eff::runSafely($userTransform->encode($internal));
        expect($encodeResult->isRight())->toBeTrue();
        
        $external = $encodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($external['user_id'])->toBe(123);
        expect($external['full_name'])->toBe('John Doe');
        expect($external['email_address'])->toBe('john@example.com');
        expect($external['birth_year'])->toBe(1990);
    });

    it('handles transformation errors gracefully', function () {
        $riskyTransform = Schema::transform(
            Schema::string(),
            Schema::number(),
            function (string $input): int {
                if ($input === 'invalid') {
                    throw new \InvalidArgumentException('Cannot convert invalid string');
                }
                return (int) $input;
            },
            function (int $input): string {
                return (string) $input;
            }
        );

        // Test successful transformation
        $validResult = Eff::runSafely($riskyTransform->decode('123'));
        expect($validResult->isRight())->toBeTrue();
        $value = $validResult->fold(fn($e) => null, fn($v) => $v);
        expect($value)->toBe(123);

        // Test transformation error
        $errorResult = Eff::runSafely($riskyTransform->decode('invalid'));
        expect($errorResult->isLeft())->toBeTrue();
        
        $error = $errorResult->fold(fn($e) => $e, fn($v) => null);
        expect($error)->toBeInstanceOf(\InvalidArgumentException::class);
        expect($error->getMessage())->toContain('Cannot convert invalid string');
    });

    it('chains multiple transformations using Effect composition', function () {
        // Transform: JSON string → Array → Normalized Array → Validated Object
        $jsonProcessingPipeline = Schema::transform(
            Schema::string(), // JSON string input
            Schema::array(Schema::string()), // Normalized array output
            function (string $jsonString): array {
                $data = json_decode($jsonString, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
                }
                return $data;
            },
            function (array $data): string {
                return json_encode($data);
            }
        );

        $normalizationTransform = Schema::transform(
            Schema::array(Schema::string()), // Raw array
            Schema::array(Schema::string()), // Normalized array
            function (array $rawData): array {
                // Normalize: trim strings, remove empty values
                return array_values(array_filter(
                    array_map('trim', $rawData),
                    fn($item) => !empty($item)
                ));
            },
            function (array $normalized): array {
                return $normalized; // Identity for encoding
            }
        );

        // Compose transformations
        $fullPipeline = function (string $jsonInput) use ($jsonProcessingPipeline, $normalizationTransform) {
            return $jsonProcessingPipeline->decode($jsonInput)
                ->flatMap(fn($array) => $normalizationTransform->decode($array));
        };

        $jsonInput = '["  hello  ", "", "world  ", "   ", "test"]';
        $result = Eff::runSafely($fullPipeline($jsonInput));
        
        expect($result->isRight())->toBeTrue();
        $processed = $result->fold(fn($e) => null, fn($v) => $v);
        expect($processed)->toBe(['hello', 'world', 'test']);
    });

    it('demonstrates roundtrip consistency for complex transformations', function () {
        // Complex bidirectional transformation for configuration data
        $configTransform = Schema::transform(
            // External config format (flat with prefixes)
            Schema::object([
                'db_host' => Schema::string(),
                'db_port' => Schema::number(),
                'db_name' => Schema::string(),
                'app_debug' => Schema::boolean(),
                'app_name' => Schema::string(),
            ], ['db_host', 'db_port', 'db_name', 'app_debug', 'app_name']),
            
            // Internal config format (nested structure)
            Schema::object([
                'database' => Schema::object([
                    'host' => Schema::string(),
                    'port' => Schema::number(),
                    'name' => Schema::string(),
                ], ['host', 'port', 'name']),
                'application' => Schema::object([
                    'debug' => Schema::boolean(),
                    'name' => Schema::string(),
                ], ['debug', 'name']),
            ], ['database', 'application']),
            
            // Decode: flat → nested
            function (array $flat): array {
                return [
                    'database' => [
                        'host' => $flat['db_host'],
                        'port' => $flat['db_port'],
                        'name' => $flat['db_name'],
                    ],
                    'application' => [
                        'debug' => $flat['app_debug'],
                        'name' => $flat['app_name'],
                    ],
                ];
            },
            
            // Encode: nested → flat
            function (array $nested): array {
                return [
                    'db_host' => $nested['database']['host'],
                    'db_port' => $nested['database']['port'],
                    'db_name' => $nested['database']['name'],
                    'app_debug' => $nested['application']['debug'],
                    'app_name' => $nested['application']['name'],
                ];
            }
        );

        $originalFlat = [
            'db_host' => 'localhost',
            'db_port' => 5432,
            'db_name' => 'myapp',
            'app_debug' => true,
            'app_name' => 'My Application',
        ];

        // Test full roundtrip: flat → nested → flat
        $decodeResult = Eff::runSafely($configTransform->decode($originalFlat));
        expect($decodeResult->isRight())->toBeTrue();
        
        $nested = $decodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($nested['database']['host'])->toBe('localhost');
        expect($nested['database']['port'])->toBe(5432);
        expect($nested['application']['debug'])->toBeTrue();

        $encodeResult = Eff::runSafely($configTransform->encode($nested));
        expect($encodeResult->isRight())->toBeTrue();
        
        $roundtripFlat = $encodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($roundtripFlat)->toBe($originalFlat);
    });

    it('handles optional properties in transformations', function () {
        $optionalTransform = Schema::transform(
            Schema::object([
                'required' => Schema::string(),
                'optional' => Schema::string()->optional(),
            ], ['required']),
            
            Schema::object([
                'required' => Schema::string(),
                'optional' => Schema::string()->nullable(),
            ], ['required']),
            
            function (array $input): array {
                return [
                    'required' => $input['required'],
                    'optional' => $input['optional'] ?? null,
                ];
            },
            
            function (array $input): array {
                $result = ['required' => $input['required']];
                if ($input['optional'] !== null) {
                    $result['optional'] = $input['optional'];
                }
                return $result;
            }
        );

        // Test with optional property present
        $withOptional = ['required' => 'value', 'optional' => 'optional_value'];
        $result1 = Eff::runSafely($optionalTransform->decode($withOptional));
        expect($result1->isRight())->toBeTrue();
        
        $transformed1 = $result1->fold(fn($e) => null, fn($v) => $v);
        expect($transformed1['optional'])->toBe('optional_value');

        // Test with optional property missing
        $withoutOptional = ['required' => 'value'];
        $result2 = Eff::runSafely($optionalTransform->decode($withoutOptional));
        expect($result2->isRight())->toBeTrue();
        
        $transformed2 = $result2->fold(fn($e) => null, fn($v) => $v);
        expect($transformed2['optional'] ?? null)->toBeNull();
    });
});