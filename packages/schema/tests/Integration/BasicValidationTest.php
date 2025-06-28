<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Schema\Parse\ParseError;

describe('Basic Schema Validation Integration', function () {
    
    it('validates complex user data with nested objects', function () {
        $userSchema = Schema::object([
            'id' => Schema::number(),
            'profile' => Schema::object([
                'name' => Schema::string()
                    ->pipe(fn($s) => Schema::minLength($s, 2))
                    ->pipe(fn($s) => Schema::maxLength($s, 50)),
                'email' => Schema::string()
                    ->pipe(fn($s) => Schema::email($s)),
                'age' => Schema::number()
                    ->pipe(fn($s) => Schema::min($s, 0))
                    ->pipe(fn($s) => Schema::max($s, 120))
                    ->optional(),
            ], ['name', 'email']),
            'roles' => Schema::array(Schema::string()),
            'isActive' => Schema::boolean(),
        ], ['id', 'profile', 'roles', 'isActive']);

        $validUser = [
            'id' => 123,
            'profile' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
            ],
            'roles' => ['admin', 'user'],
            'isActive' => true,
        ];

        // Test valid data
        $result = Run::syncResult($userSchema->decode($validUser));
        expect($result->isSuccess())->toBeTrue();
        
        $decoded = $result->getValueOrNull();
        expect($decoded)->toBe($validUser);
    });

    it('properly handles validation errors with detailed messages', function () {
        $schema = Schema::object([
            'name' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 3)),
            'age' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
        ], ['name', 'age']);

        $invalidData = [
            'name' => 'Jo', // Too short
            'age' => -5,    // Below minimum
        ];

        $result = Run::syncResult($schema->decode($invalidData));
        expect($result->isFailure())->toBeTrue();
        
        $error = $result->getErrorOrNull();
        expect($error)->toBeInstanceOf(ParseError::class);
        expect($error->getFormattedMessage())->toContain('minLength');
    });

    it('validates union types correctly', function () {
        $responseSchema = Schema::union([
            Schema::object(['success' => Schema::literal(true), 'data' => Schema::string()], ['success', 'data']),
            Schema::object(['success' => Schema::literal(false), 'error' => Schema::string()], ['success', 'error']),
        ]);

        // Test success case
        $successData = ['success' => true, 'data' => 'Operation completed'];
        expect($responseSchema->is($successData))->toBeTrue();

        // Test error case
        $errorData = ['success' => false, 'error' => 'Something went wrong'];
        expect($responseSchema->is($errorData))->toBeTrue();

        // Test invalid case
        $invalidData = ['success' => true, 'error' => 'Invalid combination'];
        expect($responseSchema->is($invalidData))->toBeFalse();
    });

    it('supports optional and nullable properties', function () {
        $schema = Schema::object([
            'required' => Schema::string(),
            'optional' => Schema::string()->optional(),
            'nullable' => Schema::string()->nullable(),
        ], ['required']);

        // All properties provided
        expect($schema->is([
            'required' => 'value',
            'optional' => 'value',
            'nullable' => 'value',
        ]))->toBeTrue();

        // Only required property
        expect($schema->is([
            'required' => 'value',
        ]))->toBeTrue();

        // With null nullable
        expect($schema->is([
            'required' => 'value',
            'nullable' => null,
        ]))->toBeTrue();

        // Missing required property
        expect($schema->is([
            'optional' => 'value',
        ]))->toBeFalse();
    });

    it('handles array validation with proper error reporting', function () {
        $schema = Schema::array(
            Schema::object([
                'id' => Schema::number(),
                'name' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 1)),
            ], ['id', 'name'])
        );

        $validArray = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ];
        
        expect($schema->is($validArray))->toBeTrue();

        $invalidArray = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 'not-a-number', 'name' => ''], // Invalid id and empty name
        ];
        
        expect($schema->is($invalidArray))->toBeFalse();
    });

    it('validates refinement schemas with custom predicates', function () {
        $evenNumberSchema = Schema::number()->pipe(fn($s) => Schema::refine(
            $s,
            fn($value) => $value % 2 === 0,
            'even-number'
        ));

        expect($evenNumberSchema->is(2))->toBeTrue();
        expect($evenNumberSchema->is(4))->toBeTrue();
        expect($evenNumberSchema->is(3))->toBeFalse();
        expect($evenNumberSchema->is(1))->toBeFalse();
    });
});