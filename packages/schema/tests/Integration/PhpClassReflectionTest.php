<?php

declare(strict_types=1);

use EffectPHP\Schema\Metadata\UniversalSchemaReflector;
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;

// Test data classes with various metadata sources
final class SimpleUser
{
    /**
     * User's full name
     * @var string
     */
    public string $name;

    /**
     * User's age
     * @var int|null
     */
    public ?int $age;

    public function __construct(string $name, ?int $age = null)
    {
        $this->name = $name;
        $this->age = $age;
    }
}

final class AdvancedUser
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
     * @var int
     * @psalm-min 0
     * @psalm-max 120
     */
    public int $age;

    /**
     * User roles
     * @var array<string>
     */
    public array $roles;

    /**
     * User preferences
     * @var array<string, mixed>
     */
    public array $preferences;

    public function __construct(
        string $name,
        string $email,
        int $age,
        array $roles = [],
        array $preferences = []
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        $this->roles = $roles;
        $this->preferences = $preferences;
    }
}

describe('PHP Class Reflection Integration', function () {
    
    beforeEach(function () {
        $this->reflector = new UniversalSchemaReflector();
        $this->compiler = new JsonSchemaCompiler();
    });

    it('generates schema from simple PHP class', function () {
        $schema = $this->reflector->fromClass(SimpleUser::class);
        
        // Test that schema validates correctly
        $validData = ['name' => 'John Doe', 'age' => 30];
        expect($schema->is($validData))->toBeTrue();
        
        $invalidData = ['name' => 123]; // Wrong type for name
        expect($schema->is($invalidData))->toBeFalse();
        
        // Test nullable handling
        $dataWithoutAge = ['name' => 'Jane Doe'];
        expect($schema->is($dataWithoutAge))->toBeTrue();
    });

    it('generates schema from class with Psalm annotations', function () {
        $schema = $this->reflector->fromClass(AdvancedUser::class);
        
        // Test valid data
        $validUser = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'roles' => ['admin', 'user'],
            'preferences' => ['theme' => 'dark']
        ];
        
        expect($schema->is($validUser))->toBeTrue();
        
        // Test constraint violations
        $userWithShortName = [
            'name' => 'J', // Too short (psalm-min-length 2)
            'email' => 'john@example.com',
            'age' => 30,
            'roles' => [],
            'preferences' => []
        ];
        
        expect($schema->is($userWithShortName))->toBeFalse();
        
        $userWithInvalidAge = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => -5, // Below minimum (psalm-min 0)
            'roles' => [],
            'preferences' => []
        ];
        
        expect($schema->is($userWithInvalidAge))->toBeFalse();
    });

    it('handles required vs optional properties correctly', function () {
        $schema = $this->reflector->fromClass(AdvancedUser::class);
        
        // All required properties must be present
        $missingEmail = [
            'name' => 'John Doe',
            'age' => 30,
            'roles' => [],
            'preferences' => []
        ];
        
        expect($schema->is($missingEmail))->toBeFalse();
        
        // Optional properties (with default values) can be omitted
        $minimalValid = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'roles' => [],
            'preferences' => []
        ];
        
        expect($schema->is($minimalValid))->toBeTrue();
    });

    it('integrates reflection with JSON Schema compilation', function () {
        $schema = $this->reflector->fromClass(AdvancedUser::class);
        $jsonSchema = $this->compiler->compile($schema->getAST());
        
        // Verify overall structure
        expect($jsonSchema['type'])->toBe('object');
        expect($jsonSchema)->toHaveKey('properties');
        expect($jsonSchema)->toHaveKey('required');
        
        // Verify properties are correctly typed
        expect($jsonSchema['properties']['name']['type'])->toBe('string');
        expect($jsonSchema['properties']['email']['type'])->toBe('string');
        expect($jsonSchema['properties']['age']['type'])->toBe('number');
        expect($jsonSchema['properties']['roles']['type'])->toBe('array');
        
        // Verify constraints are applied
        expect($jsonSchema['properties']['name'])->toHaveKey('description');
        expect($jsonSchema['properties']['age'])->toHaveKey('description');
        
        // Verify required fields
        expect($jsonSchema['required'])->toContain('name');
        expect($jsonSchema['required'])->toContain('email');
        expect($jsonSchema['required'])->toContain('age');
    });

    it('handles complex validation with Effect composition', function () {
        $schema = $this->reflector->fromClass(AdvancedUser::class);
        
        $testData = [
            'name' => 'John Smith',
            'email' => 'john.smith@example.com',
            'age' => 35,
            'roles' => ['admin', 'developer'],
            'preferences' => [
                'theme' => 'dark',
                'notifications' => true,
                'language' => 'en'
            ]
        ];
        
        // Use Effect directly for more detailed validation
        $decodeEffect = $schema->decode($testData);
        $result = Run::syncResult($decodeEffect);
        
        expect($result->isSuccess())->toBeTrue();
        
        $decoded = $result->getValueOrNull();
        expect($decoded)->toBe($testData);
        
        // Test encoding back
        $encodeEffect = $schema->encode($decoded);
        $encodeResult = Run::syncResult($encodeEffect);
        
        expect($encodeResult->isSuccess())->toBeTrue();
        
        $encoded = $encodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($encoded)->toBe($testData);
    });

    it('generates schema from object instance', function () {
        $user = new SimpleUser('Jane Doe', 25);
        $schema = $this->reflector->fromObject($user);
        
        $userData = ['name' => 'Bob Wilson', 'age' => 40];
        expect($schema->is($userData))->toBeTrue();
        
        $invalidData = ['name' => 123, 'age' => 'not-a-number'];
        expect($schema->is($invalidData))->toBeFalse();
    });

    it('handles array type annotations correctly', function () {
        $schema = $this->reflector->fromClass(AdvancedUser::class);
        
        $validWithArrays = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'roles' => ['admin', 'user', 'moderator'], // array<string>
            'preferences' => ['setting1' => 'value1', 'setting2' => 'value2'] // array<string, mixed>
        ];
        
        expect($schema->is($validWithArrays))->toBeTrue();
        
        $invalidRoles = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'roles' => 'not-an-array', // Should be array
            'preferences' => []
        ];
        
        expect($schema->is($invalidRoles))->toBeFalse();
    });

    it('demonstrates end-to-end workflow: Class → Schema → JSON Schema → LLM Integration', function () {
        // 1. Generate schema from PHP class
        $schema = $this->reflector->fromClass(AdvancedUser::class);
        
        // 2. Compile to JSON Schema for LLM
        $jsonSchema = $this->compiler->compile($schema->getAST());
        
        // 3. Verify JSON Schema is valid and complete
        expect($jsonSchema)->toBeArray();
        expect(json_encode($jsonSchema))->toBeString();
        
        // 4. Simulate LLM response validation
        $llmResponse = [
            'name' => 'AI Generated User',
            'email' => 'ai@example.com',
            'age' => 28,
            'roles' => ['user'],
            'preferences' => ['ai_generated' => true]
        ];
        
        // 5. Validate LLM response using our schema
        $validationResult = Run::syncResult($schema->decode($llmResponse));
        expect($validationResult->isSuccess())->toBeTrue();
        
        // 6. Successfully decoded data can be used to create PHP object
        $validatedData = $validationResult->fold(fn($e) => null, fn($v) => $v);
        $userObject = new AdvancedUser(
            $validatedData['name'],
            $validatedData['email'],
            $validatedData['age'],
            $validatedData['roles'],
            $validatedData['preferences']
        );
        
        expect($userObject->name)->toBe('AI Generated User');
        expect($userObject->email)->toBe('ai@example.com');
        expect($userObject->age)->toBe(28);
    });
});