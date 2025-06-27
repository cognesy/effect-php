<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;
use EffectPHP\Schema\Metadata\UniversalSchemaReflector;
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;
use EffectPHP\Core\Eff;

/**
 * Example classes for end-to-end testing
 */
final class UserProfile
{
    /**
     * User's full name
     * @var string
     * @psalm-min-length 2
     * @psalm-max-length 50
     */
    public string $name;

    /**
     * User email address
     * @var string
     * @psalm-pattern /^[^\s@]+@[^\s@]+\.[^\s@]+$/
     */
    public string $email;

    /**
     * User age in years
     * @var int|null
     * @psalm-min 0
     * @psalm-max 120
     */
    public ?int $age;

    /**
     * User preferences
     * @var array<string>
     */
    public array $preferences;

    public function __construct(string $name, string $email, ?int $age = null, array $preferences = [])
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        $this->preferences = $preferences;
    }
}

final class ApiResponse
{
    /**
     * Response status
     * @var string
     */
    public string $status;

    /**
     * Response data
     * @var array<string, mixed>|null
     */
    public ?array $data;

    /**
     * Error message if any
     * @var string|null
     */
    public ?string $error;

    public function __construct(string $status, ?array $data = null, ?string $error = null)
    {
        $this->status = $status;
        $this->data = $data;
        $this->error = $error;
    }
}

describe('End-to-End Schema Workflow Integration', function () {
    
    beforeEach(function () {
        $this->reflector = new UniversalSchemaReflector();
        $this->compiler = new JsonSchemaCompiler();
    });

    it('demonstrates complete LLM integration workflow', function () {
        // Step 1: Generate schema from PHP class
        $userSchema = $this->reflector->fromClass(UserProfile::class);
        
        // Step 2: Compile to JSON Schema for LLM
        $jsonSchema = $this->compiler->compile($userSchema->getAST());
        
        // Step 3: Verify JSON Schema is LLM-ready
        expect($jsonSchema)->toHaveKey('type', 'object');
        expect($jsonSchema)->toHaveKey('properties');
        expect($jsonSchema)->toHaveKey('required');
        
        // JSON Schema should be serializable for LLM API calls
        $jsonSchemaString = json_encode($jsonSchema);
        expect($jsonSchemaString)->toBeString();
        expect(strlen($jsonSchemaString))->toBeGreaterThan(100);
        
        // Step 4: Simulate LLM structured output response
        $llmResponse = [
            'name' => 'Alice Johnson',
            'email' => 'alice.johnson@company.com',
            'age' => 28,
            'preferences' => ['dark-mode', 'email-notifications', 'weekly-digest']
        ];
        
        // Step 5: Validate LLM response
        $validationResult = Eff::runSafely($userSchema->decode($llmResponse));
        expect($validationResult->isRight())->toBeTrue();
        
        $validatedData = $validationResult->fold(fn($e) => null, fn($v) => $v);
        expect($validatedData)->toBe($llmResponse);
        
        // Step 6: Create PHP object from validated data
        $userProfile = new UserProfile(
            $validatedData['name'],
            $validatedData['email'],
            $validatedData['age'],
            $validatedData['preferences']
        );
        
        expect($userProfile->name)->toBe('Alice Johnson');
        expect($userProfile->email)->toBe('alice.johnson@company.com');
        expect($userProfile->age)->toBe(28);
        expect($userProfile->preferences)->toHaveCount(3);
    });

    it('handles API integration with validation and transformation', function () {
        // Create schemas for API request/response
        $apiRequestSchema = Schema::object([
            'action' => Schema::union([
                Schema::literal('create_user'),
                Schema::literal('update_user'),
                Schema::literal('delete_user'),
            ]),
            'payload' => Schema::object([
                'name' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 2)),
                'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
                'age' => Schema::number()->pipe(fn($s) => Schema::min($s, 0))->optional(),
            ], ['name', 'email']),
            'metadata' => Schema::object([
                'requestId' => Schema::string(),
                'timestamp' => Schema::string(),
                'source' => Schema::string(),
            ], ['requestId', 'timestamp', 'source']),
        ], ['action', 'payload', 'metadata']);

        // Transform and validate API request
        $apiRequest = [
            'action' => 'create_user',
            'payload' => [
                'name' => 'Bob Smith',
                'email' => 'bob.smith@example.com',
                'age' => 35,
            ],
            'metadata' => [
                'requestId' => 'req-123-456',
                'timestamp' => '2024-01-15T10:30:00Z',
                'source' => 'web-app',
            ]
        ];

        // Validate incoming request
        $requestResult = Eff::runSafely($apiRequestSchema->decode($apiRequest));
        expect($requestResult->isRight())->toBeTrue();

        $validatedRequest = $requestResult->fold(fn($e) => null, fn($v) => $v);

        // Process request and create response
        $responseSchema = $this->reflector->fromClass(ApiResponse::class);
        
        $successResponse = [
            'status' => 'success',
            'data' => [
                'userId' => 12345,
                'created' => true,
                'profile' => $validatedRequest['payload']
            ],
            'error' => null,
        ];

        $responseResult = Eff::runSafely($responseSchema->decode($successResponse));
        expect($responseResult->isRight())->toBeTrue();
    });

    it('demonstrates schema evolution and migration', function () {
        // V1 User Schema
        $userV1Schema = Schema::object([
            'name' => Schema::string(),
            'email' => Schema::string(),
        ], ['name', 'email']);

        // V2 User Schema (with additional fields)
        $userV2Schema = Schema::object([
            'name' => Schema::string(),
            'email' => Schema::string(),
            'age' => Schema::number()->optional(),
            'preferences' => Schema::array(Schema::string())->optional(),
            'version' => Schema::literal('2.0'),
        ], ['name', 'email', 'version']);

        // Migration transform: V1 → V2
        $migrationTransform = Schema::transform(
            $userV1Schema,
            $userV2Schema,
            function (array $v1Data): array {
                return array_merge($v1Data, [
                    'age' => null,
                    'preferences' => [],
                    'version' => '2.0',
                ]);
            },
            function (array $v2Data): array {
                // Downgrade: remove V2-specific fields
                return [
                    'name' => $v2Data['name'],
                    'email' => $v2Data['email'],
                ];
            }
        );

        // Test migration
        $v1User = [
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
        ];

        $migrationResult = Eff::runSafely($migrationTransform->decode($v1User));
        expect($migrationResult->isRight())->toBeTrue();

        $v2User = $migrationResult->fold(fn($e) => null, fn($v) => $v);
        expect($v2User['name'])->toBe('Legacy User');
        expect($v2User['email'])->toBe('legacy@example.com');
        expect($v2User['age'] ?? null)->toBeNull();
        expect($v2User['preferences'])->toBe([]);
        expect($v2User['version'])->toBe('2.0');

        // Test reverse migration
        $downgradeResult = Eff::runSafely($migrationTransform->encode($v2User));
        expect($downgradeResult->isRight())->toBeTrue();

        $downgradedUser = $downgradeResult->fold(fn($e) => null, fn($v) => $v);
        expect($downgradedUser)->toBe($v1User);
    });

    it('validates complex nested data structures with parallel processing', function () {
        // Complex organization schema
        $organizationSchema = Schema::object([
            'info' => Schema::object([
                'name' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 1)),
                'website' => Schema::string()->optional(),
                'founded' => Schema::number()->pipe(fn($s) => Schema::min($s, 1800)),
            ], ['name', 'founded']),
            'departments' => Schema::array(
                Schema::object([
                    'name' => Schema::string(),
                    'budget' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
                    'employees' => Schema::array(
                        Schema::object([
                            'id' => Schema::number(),
                            'name' => Schema::string(),
                            'position' => Schema::string(),
                            'salary' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
                        ], ['id', 'name', 'position', 'salary'])
                    ),
                ], ['name', 'budget', 'employees'])
            ),
            'metadata' => Schema::object([
                'lastUpdated' => Schema::string(),
                'version' => Schema::string(),
                'source' => Schema::string(),
            ], ['lastUpdated', 'version', 'source']),
        ], ['info', 'departments', 'metadata']);

        $organizationData = [
            'info' => [
                'name' => 'TechCorp Inc.',
                'website' => 'https://techcorp.example.com',
                'founded' => 2010,
            ],
            'departments' => [
                [
                    'name' => 'Engineering',
                    'budget' => 2000000,
                    'employees' => [
                        ['id' => 1, 'name' => 'Alice Developer', 'position' => 'Senior Developer', 'salary' => 120000],
                        ['id' => 2, 'name' => 'Bob Engineer', 'position' => 'Tech Lead', 'salary' => 140000],
                    ]
                ],
                [
                    'name' => 'Marketing',
                    'budget' => 500000,
                    'employees' => [
                        ['id' => 3, 'name' => 'Carol Marketer', 'position' => 'Marketing Manager', 'salary' => 80000],
                    ]
                ],
            ],
            'metadata' => [
                'lastUpdated' => '2024-01-15T10:30:00Z',
                'version' => '1.0',
                'source' => 'hr-system',
            ],
        ];

        // This tests parallel validation of nested arrays and objects
        $startTime = microtime(true);
        $result = Eff::runSafely($organizationSchema->decode($organizationData));
        $endTime = microtime(true);

        expect($result->isRight())->toBeTrue();

        $validated = $result->fold(fn($e) => null, fn($v) => $v);
        expect($validated['info']['name'])->toBe('TechCorp Inc.');
        expect($validated['departments'])->toHaveCount(2);
        expect($validated['departments'][0]['employees'])->toHaveCount(2);
        expect($validated['departments'][1]['employees'])->toHaveCount(1);

        // Parallel processing should be reasonably fast
        $duration = $endTime - $startTime;
        expect($duration)->toBeLessThan(0.5); // Should complete in less than 500ms
    });

    it('demonstrates error aggregation across complex validation scenarios', function () {
        $complexSchema = Schema::object([
            'users' => Schema::array(
                Schema::object([
                    'name' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 2)),
                    'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
                    'age' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
                    'settings' => Schema::object([
                        'theme' => Schema::union([
                            Schema::literal('light'),
                            Schema::literal('dark'),
                        ]),
                        'notifications' => Schema::boolean(),
                    ], ['theme', 'notifications']),
                ], ['name', 'email', 'age', 'settings'])
            ),
            'config' => Schema::object([
                'version' => Schema::string(),
                'features' => Schema::array(Schema::string()),
            ], ['version', 'features']),
        ], ['users', 'config']);

        // Data with multiple validation errors at different levels
        $invalidData = [
            'users' => [
                [
                    'name' => 'A', // Too short
                    'email' => 'invalid-email', // Invalid format
                    'age' => -5, // Negative age
                    'settings' => [
                        'theme' => 'blue', // Invalid theme
                        'notifications' => 'yes', // Should be boolean
                    ]
                ],
                [
                    'name' => 'Valid User',
                    'email' => 'valid@example.com',
                    'age' => 25,
                    'settings' => [
                        'theme' => 'dark',
                        'notifications' => true,
                    ]
                ]
            ],
            // Missing required 'config' field
        ];

        $result = Eff::runSafely($complexSchema->decode($invalidData));
        expect($result->isLeft())->toBeTrue();

        $error = $result->fold(fn($e) => $e, fn($v) => null);
        expect($error)->toBeInstanceOf(\EffectPHP\Schema\Parse\ParseError::class);

        // Verify we collected multiple validation errors
        $formattedMessage = $error->getFormattedMessage();
        expect(strlen($formattedMessage))->toBeGreaterThan(40); // Should have substantial error details
    });
});