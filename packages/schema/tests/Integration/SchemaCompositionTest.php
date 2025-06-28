<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;

describe('Schema Composition and Extension Integration', function () {
    
    it('composes schemas using the compose method', function () {
        $baseSchema = Schema::object([
            'id' => Schema::number(),
            'name' => Schema::string(),
        ], ['id', 'name']);

        $extendedSchema = Schema::object([
            'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
            'active' => Schema::boolean(),
        ], ['email', 'active']);

        $composedSchema = $baseSchema->compose($extendedSchema);

        // Test data that should be valid for the composed schema
        $validData = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'active' => true,
        ];

        expect($composedSchema->is($validData))->toBeTrue();

        // Test partial data (missing fields from either schema)
        $incompleteData = [
            'id' => 123,
            'name' => 'John Doe',
            // Missing email and active from extended schema
        ];

        expect($composedSchema->is($incompleteData))->toBeFalse();
    });

    it('extends schemas with additional validation layers', function () {
        $userSchema = Schema::object([
            'username' => Schema::string()
                ->pipe(fn($s) => Schema::minLength($s, 3))
                ->pipe(fn($s) => Schema::maxLength($s, 20)),
            'age' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
        ], ['username', 'age']);

        // Add additional business logic validation
        $businessRulesSchema = Schema::refine(
            $userSchema,
            function (array $user): bool {
                // Business rule: users under 13 cannot have usernames starting with 'admin'
                if ($user['age'] < 13 && str_starts_with(strtolower($user['username']), 'admin')) {
                    return false;
                }
                return true;
            },
            'business-rules'
        );

        // Test valid user
        $validUser = ['username' => 'johndoe', 'age' => 25];
        expect($businessRulesSchema->is($validUser))->toBeTrue();

        // Test business rule violation
        $invalidUser = ['username' => 'admin123', 'age' => 10];
        expect($businessRulesSchema->is($invalidUser))->toBeFalse();

        // Test user that passes business rules but is still young
        $validYoungUser = ['username' => 'kiduser', 'age' => 10];
        expect($businessRulesSchema->is($validYoungUser))->toBeTrue();
    });

    it('creates schema hierarchies with inheritance-like behavior', function () {
        // Base entity schema
        $baseEntitySchema = Schema::object([
            'id' => Schema::number(),
            'createdAt' => Schema::string(),
            'updatedAt' => Schema::string(),
        ], ['id', 'createdAt', 'updatedAt']);

        // User entity extends base entity
        $userEntitySchema = $baseEntitySchema->compose(Schema::object([
            'username' => Schema::string(),
            'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
        ], ['username', 'email']));

        // Admin user extends user entity
        $adminUserSchema = $userEntitySchema->compose(Schema::object([
            'permissions' => Schema::array(Schema::string()),
            'role' => Schema::literal('admin'),
        ], ['permissions', 'role']));

        $adminData = [
            'id' => 1,
            'createdAt' => '2024-01-01T00:00:00Z',
            'updatedAt' => '2024-01-01T00:00:00Z',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'permissions' => ['read', 'write', 'delete'],
            'role' => 'admin',
        ];

        expect($adminUserSchema->is($adminData))->toBeTrue();

        // Test that admin schema rejects non-admin role
        $nonAdminData = array_merge($adminData, ['role' => 'user']);
        expect($adminUserSchema->is($nonAdminData))->toBeFalse();
    });

    it('implements conditional schema composition based on discriminators', function () {
        // Discriminated union pattern
        $shapeSchema = Schema::union([
            // Circle
            Schema::object([
                'type' => Schema::literal('circle'),
                'radius' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
            ], ['type', 'radius']),
            
            // Rectangle
            Schema::object([
                'type' => Schema::literal('rectangle'),
                'width' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
                'height' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
            ], ['type', 'width', 'height']),
            
            // Triangle
            Schema::object([
                'type' => Schema::literal('triangle'),
                'base' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
                'height' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
            ], ['type', 'base', 'height']),
        ]);

        // Test each shape type
        $circle = ['type' => 'circle', 'radius' => 5.0];
        expect($shapeSchema->is($circle))->toBeTrue();

        $rectangle = ['type' => 'rectangle', 'width' => 10.0, 'height' => 5.0];
        expect($shapeSchema->is($rectangle))->toBeTrue();

        $triangle = ['type' => 'triangle', 'base' => 8.0, 'height' => 6.0];
        expect($shapeSchema->is($triangle))->toBeTrue();

        // Test invalid combinations
        $invalidShape = ['type' => 'circle', 'width' => 10.0]; // Circle with width
        expect($shapeSchema->is($invalidShape))->toBeFalse();
    });

    it('composes schemas with different optional/required field strategies', function () {
        $coreDataSchema = Schema::object([
            'id' => Schema::number(),
            'name' => Schema::string(),
        ], ['id', 'name']);

        $optionalMetadataSchema = Schema::object([
            'description' => Schema::string()->optional(),
            'tags' => Schema::array(Schema::string())->optional(),
            'metadata' => Schema::object([
                'source' => Schema::string(),
                'version' => Schema::string(),
            ], ['source', 'version'])->optional(),
        ], []); // No required fields in metadata

        $composedSchema = $coreDataSchema->compose($optionalMetadataSchema);

        // Test with core data only
        $coreOnly = ['id' => 1, 'name' => 'Test Item'];
        expect($composedSchema->is($coreOnly))->toBeTrue();

        // Test with full data
        $fullData = [
            'id' => 1,
            'name' => 'Test Item',
            'description' => 'A test item',
            'tags' => ['test', 'example'],
            'metadata' => [
                'source' => 'api',
                'version' => '1.0',
            ],
        ];
        expect($composedSchema->is($fullData))->toBeTrue();

        // Test with partial metadata
        $partialMetadata = [
            'id' => 1,
            'name' => 'Test Item',
            'description' => 'A test item',
        ];
        expect($composedSchema->is($partialMetadata))->toBeTrue();
    });

    it('demonstrates complex schema composition for API versioning', function () {
        // Base API response schema
        $baseResponseSchema = Schema::object([
            'status' => Schema::union([
                Schema::literal('success'),
                Schema::literal('error'),
            ]),
            'timestamp' => Schema::string(),
        ], ['status', 'timestamp']);

        // V1 API specific fields
        $v1ResponseSchema = $baseResponseSchema->compose(Schema::object([
            'data' => Schema::union([
                Schema::string(),
                Schema::number(),
                Schema::array(Schema::string()),
            ])->optional(),
            'error_message' => Schema::string()->optional(),
        ], []));

        // V2 API specific fields (enhanced error handling)
        $v2ResponseSchema = $baseResponseSchema->compose(Schema::object([
            'data' => Schema::object([
                'content' => Schema::union([
                    Schema::string(),
                    Schema::number(),
                    Schema::array(Schema::string()),
                ]),
                'pagination' => Schema::object([
                    'page' => Schema::number(),
                    'total' => Schema::number(),
                ], ['page', 'total'])->optional(),
            ], ['content'])->optional(),
            'errors' => Schema::array(Schema::object([
                'code' => Schema::string(),
                'message' => Schema::string(),
                'field' => Schema::string()->optional(),
            ], ['code', 'message']))->optional(),
            'version' => Schema::literal('2.0'),
        ], ['version']));

        // Test V1 response
        $v1Success = [
            'status' => 'success',
            'timestamp' => '2024-01-01T00:00:00Z',
            'data' => ['item1', 'item2', 'item3'],
        ];
        expect($v1ResponseSchema->is($v1Success))->toBeTrue();

        $v1Error = [
            'status' => 'error',
            'timestamp' => '2024-01-01T00:00:00Z',
            'error_message' => 'Something went wrong',
        ];
        expect($v1ResponseSchema->is($v1Error))->toBeTrue();

        // Test V2 response
        $v2Success = [
            'status' => 'success',
            'timestamp' => '2024-01-01T00:00:00Z',
            'version' => '2.0',
            'data' => [
                'content' => ['item1', 'item2'],
                'pagination' => ['page' => 1, 'total' => 10],
            ],
        ];
        expect($v2ResponseSchema->is($v2Success))->toBeTrue();

        $v2Error = [
            'status' => 'error',
            'timestamp' => '2024-01-01T00:00:00Z',
            'version' => '2.0',
            'errors' => [
                ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid field', 'field' => 'email'],
                ['code' => 'AUTH_ERROR', 'message' => 'Unauthorized'],
            ],
        ];
        expect($v2ResponseSchema->is($v2Error))->toBeTrue();

        // Test that V2 schema rejects V1-style responses
        expect($v2ResponseSchema->is($v1Success))->toBeFalse();
    });

    it('validates schema composition with Effect-based processing', function () {
        $personalInfoSchema = Schema::object([
            'firstName' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 1)),
            'lastName' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 1)),
        ], ['firstName', 'lastName']);

        $contactInfoSchema = Schema::object([
            'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
            'phone' => Schema::string()->optional(),
        ], ['email']);

        $preferencesSchema = Schema::object([
            'newsletter' => Schema::boolean(),
            'notifications' => Schema::boolean(),
        ], ['newsletter', 'notifications']);

        // Compose all schemas
        $fullUserSchema = $personalInfoSchema
            ->compose($contactInfoSchema)
            ->compose($preferencesSchema);

        $userData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'newsletter' => true,
            'notifications' => false,
        ];

        // Test using Effect directly
        $result = Run::syncResult($fullUserSchema->decode($userData));
        expect($result->isSuccess())->toBeTrue();

        $decoded = $result->getValueOrNull();
        expect($decoded)->toBe($userData);

        // Test encoding
        $encodeResult = Run::syncResult($fullUserSchema->encode($decoded));
        expect($encodeResult->isSuccess())->toBeTrue();

        $encoded = $encodeResult->fold(fn($e) => null, fn($v) => $v);
        expect($encoded)->toBe($userData);
    });
});
