<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\Parse\ParseError;

describe('Effect Composition and Error Handling Integration', function () {
    
    it('demonstrates proper Effect composition in validation pipelines', function () {
        $userSchema = Schema::object([
            'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
            'password' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 8)),
        ], ['email', 'password']);

        $hashingSchema = Schema::transform(
            $userSchema,
            Schema::object([
                'email' => Schema::string(),
                'passwordHash' => Schema::string(),
            ], ['email', 'passwordHash']),
            function (array $userData): array {
                return [
                    'email' => $userData['email'],
                    'passwordHash' => password_hash($userData['password'], PASSWORD_DEFAULT),
                ];
            },
            function (array $hashedData): array {
                throw new \RuntimeException('Cannot reverse password hashing');
            }
        );

        // Test successful composition
        $validInput = ['email' => 'user@example.com', 'password' => 'securepassword123'];
        
        $effect = $hashingSchema->decode($validInput);
        $result = Eff::runSafely($effect);
        
        expect($result->isRight())->toBeTrue();
        
        $processed = $result->fold(fn($e) => null, fn($v) => $v);
        expect($processed)->toHaveKey('email', 'user@example.com');
        expect($processed)->toHaveKey('passwordHash');
        expect($processed['passwordHash'])->toBeString();
        expect(strlen($processed['passwordHash']))->toBeGreaterThan(10);
    });

    it('properly handles and aggregates validation errors from parallel operations', function () {
        $complexSchema = Schema::object([
            'users' => Schema::array(
                Schema::object([
                    'name' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 2)),
                    'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
                    'age' => Schema::number()->pipe(fn($s) => Schema::min($s, 0)),
                ], ['name', 'email', 'age'])
            ),
            'metadata' => Schema::object([
                'version' => Schema::string(),
                'timestamp' => Schema::number(),
            ], ['version', 'timestamp']),
        ], ['users', 'metadata']);

        // Data with multiple validation errors
        $invalidData = [
            'users' => [
                ['name' => 'J', 'email' => 'invalid-email', 'age' => -5], // All fields invalid
                ['name' => 'Valid User', 'email' => 'valid@example.com', 'age' => 25], // Valid
                ['name' => '', 'email' => 'another@invalid', 'age' => 150], // Multiple issues
            ],
            'metadata' => [
                'version' => '1.0.0',
                // Missing required 'timestamp'
            ]
        ];

        $result = Eff::runSafely($complexSchema->decode($invalidData));
        
        expect($result->isLeft())->toBeTrue();
        
        $error = $result->fold(fn($e) => $e, fn($v) => null);
        expect($error)->toBeInstanceOf(ParseError::class);
        
        // Verify error contains multiple issues
        $issues = $error->getIssues();
        expect($issues)->not->toBeEmpty();
        
        $formattedMessage = $error->getFormattedMessage();
        expect($formattedMessage)->toBeString();
        expect(strlen($formattedMessage))->toBeGreaterThan(10);
    });

    it('demonstrates Effect chaining with flatMap for dependent validations', function () {
        // Schema that validates user data and then checks uniqueness
        $userRegistrationSchema = Schema::object([
            'username' => Schema::string()->pipe(fn($s) => Schema::minLength($s, 3)),
            'email' => Schema::string()->pipe(fn($s) => Schema::email($s)),
        ], ['username', 'email']);

        // Simulate a validation pipeline with dependent checks
        $validateUniqueUsername = function (array $userData) {
            // Simulate async uniqueness check
            if ($userData['username'] === 'taken_username') {
                return Eff::fail(new \RuntimeException('Username already taken'));
            }
            return Eff::succeed($userData);
        };

        $validateUniqueEmail = function (array $userData) {
            // Simulate async email uniqueness check
            if ($userData['email'] === 'taken@example.com') {
                return Eff::fail(new \RuntimeException('Email already registered'));
            }
            return Eff::succeed($userData);
        };

        // Compose the entire validation pipeline
        $registrationPipeline = function (array $input) use ($userRegistrationSchema, $validateUniqueUsername, $validateUniqueEmail) {
            return $userRegistrationSchema->decode($input)
                ->flatMap($validateUniqueUsername)
                ->flatMap($validateUniqueEmail);
        };

        // Test successful registration
        $validRegistration = ['username' => 'newuser', 'email' => 'new@example.com'];
        $result = Eff::runSafely($registrationPipeline($validRegistration));
        
        expect($result->isRight())->toBeTrue();
        $validated = $result->fold(fn($e) => null, fn($v) => $v);
        expect($validated)->toBe($validRegistration);

        // Test username conflict
        $conflictUsername = ['username' => 'taken_username', 'email' => 'new@example.com'];
        $conflictResult = Eff::runSafely($registrationPipeline($conflictUsername));
        
        expect($conflictResult->isLeft())->toBeTrue();
        $error = $conflictResult->fold(fn($e) => $e, fn($v) => null);
        expect($error->getMessage())->toContain('Username already taken');

        // Test email conflict
        $conflictEmail = ['username' => 'newuser', 'email' => 'taken@example.com'];
        $emailResult = Eff::runSafely($registrationPipeline($conflictEmail));
        
        expect($emailResult->isLeft())->toBeTrue();
        $emailError = $emailResult->fold(fn($e) => $e, fn($v) => null);
        expect($emailError->getMessage())->toContain('Email already registered');
    });

    it('handles complex async validation scenarios with Effect composition', function () {
        // Simulate async operations that return Effects
        $fetchUserProfile = function (int $userId) {
            return Eff::succeed(['id' => $userId, 'name' => "User {$userId}", 'active' => true]);
        };

        $validatePermissions = function (array $user, string $action) {
            if (!$user['active']) {
                return Eff::fail(new \RuntimeException('User account is inactive'));
            }
            if ($action === 'admin' && $user['id'] !== 1) {
                return Eff::fail(new \RuntimeException('Insufficient permissions'));
            }
            return Eff::succeed($user);
        };

        $requestSchema = Schema::object([
            'userId' => Schema::number()->pipe(fn($s) => Schema::min($s, 1)),
            'action' => Schema::union([
                Schema::literal('read'),
                Schema::literal('write'),
                Schema::literal('admin'),
            ]),
        ], ['userId', 'action']);

        // Compose the authorization pipeline
        $authorizationPipeline = function (array $request) use ($requestSchema, $fetchUserProfile, $validatePermissions) {
            return $requestSchema->decode($request)
                ->flatMap(function ($validRequest) use ($fetchUserProfile, $validatePermissions) {
                    return $fetchUserProfile($validRequest['userId'])
                        ->flatMap(fn($user) => $validatePermissions($user, $validRequest['action']))
                        ->map(fn($user) => ['user' => $user, 'action' => $validRequest['action']]);
                });
        };

        // Test successful authorization
        $validRequest = ['userId' => 1, 'action' => 'admin'];
        $result = Eff::runSafely($authorizationPipeline($validRequest));
        
        expect($result->isRight())->toBeTrue();
        $authorized = $result->fold(fn($e) => null, fn($v) => $v);
        expect($authorized['user']['id'])->toBe(1);
        expect($authorized['action'])->toBe('admin');

        // Test permission denied
        $deniedRequest = ['userId' => 2, 'action' => 'admin'];
        $deniedResult = Eff::runSafely($authorizationPipeline($deniedRequest));
        
        expect($deniedResult->isLeft())->toBeTrue();
        $error = $deniedResult->fold(fn($e) => $e, fn($v) => null);
        expect($error->getMessage())->toContain('Insufficient permissions');
    });

    it('demonstrates proper error recovery with catchError', function () {
        $riskySchema = Schema::string()->pipe(fn($s) => Schema::refine(
            $s,
            function ($value) {
                if ($value === 'error') {
                    throw new \RuntimeException('Simulated processing error');
                }
                return strlen($value) > 3;
            },
            'risky-validation'
        ));

        $recoveringSchema = function (string $input) use ($riskySchema) {
            return $riskySchema->decode($input)
                ->catchError(
                    \RuntimeException::class,
                    fn(\Throwable $e) => Eff::succeed('default-fallback-value')
                );
        };

        // Test normal successful case
        $normalResult = Eff::runSafely($recoveringSchema('valid-input'));
        expect($normalResult->isRight())->toBeTrue();
        $normal = $normalResult->fold(fn($e) => null, fn($v) => $v);
        expect($normal)->toBe('valid-input');

        // Test error recovery
        $errorResult = Eff::runSafely($recoveringSchema('error'));
        expect($errorResult->isRight())->toBeTrue();
        $recovered = $errorResult->fold(fn($e) => null, fn($v) => $v);
        expect($recovered)->toBe('default-fallback-value');

        // Test validation failure (not recovered)
        $validationFailResult = Eff::runSafely($recoveringSchema('hi'));
        expect($validationFailResult->isLeft())->toBeTrue();
    });

    it('validates parallel operations maintain Effect composition principles', function () {
        $batchSchema = Schema::object([
            'items' => Schema::array(
                Schema::object([
                    'id' => Schema::number(),
                    'data' => Schema::string(),
                ], ['id', 'data'])
            ),
        ], ['items']);

        $largeDataset = [
            'items' => array_map(
                fn($i) => ['id' => $i, 'data' => "item-{$i}"],
                range(1, 100)
            )
        ];

        // This should use parallel validation internally
        $startTime = microtime(true);
        $result = Eff::runSafely($batchSchema->decode($largeDataset));
        $endTime = microtime(true);

        expect($result->isRight())->toBeTrue();
        
        $validated = $result->fold(fn($e) => null, fn($v) => $v);
        expect($validated['items'])->toHaveCount(100);
        expect($validated['items'][0]['id'])->toBe(1);
        expect($validated['items'][99]['id'])->toBe(100);

        // Performance should be reasonable (parallel processing)
        $duration = $endTime - $startTime;
        expect($duration)->toBeLessThan(1.0); // Should complete in less than 1 second
    });
});