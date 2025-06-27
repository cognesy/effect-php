<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;

describe('JSON Schema Compilation Integration', function () {
    
    beforeEach(function () {
        $this->compiler = new JsonSchemaCompiler();
    });

    it('compiles basic types to correct JSON Schema', function () {
        $stringSchema = Schema::string();
        $result = $this->compiler->compile($stringSchema->getAST());
        
        expect($result)->toBe(['type' => 'string']);

        $numberSchema = Schema::number();
        $result = $this->compiler->compile($numberSchema->getAST());
        
        expect($result)->toBe(['type' => 'number']);

        $booleanSchema = Schema::boolean();
        $result = $this->compiler->compile($booleanSchema->getAST());
        
        expect($result)->toBe(['type' => 'boolean']);
    });

    it('compiles literal schemas with const values', function () {
        $literalSchema = Schema::literal('hello');
        $result = $this->compiler->compile($literalSchema->getAST());
        
        expect($result)->toBe(['const' => 'hello']);

        $numberLiteral = Schema::literal(42);
        $result = $this->compiler->compile($numberLiteral->getAST());
        
        expect($result)->toBe(['const' => 42]);
    });

    it('compiles array schemas with item constraints', function () {
        $arraySchema = Schema::array(Schema::string());
        $result = $this->compiler->compile($arraySchema->getAST());
        
        expect($result)->toBe([
            'type' => 'array',
            'items' => ['type' => 'string']
        ]);
    });

    it('compiles object schemas with properties and required fields', function () {
        $objectSchema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::number(),
            'email' => Schema::string()->optional(),
        ], ['name', 'age']);

        $result = $this->compiler->compile($objectSchema->getAST());
        
        expect($result)->toBe([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'number'],
                'email' => ['type' => 'string'],
            ],
            'required' => ['name', 'age']
        ]);
    });

    it('compiles union schemas with oneOf', function () {
        $unionSchema = Schema::union([
            Schema::string(),
            Schema::number(),
            Schema::boolean(),
        ]);

        $result = $this->compiler->compile($unionSchema->getAST());
        
        expect($result)->toBe([
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'number'],
                ['type' => 'boolean'],
            ]
        ]);
    });

    it('compiles schemas with validation constraints and annotations', function () {
        $constrainedSchema = Schema::string()
            ->pipe(fn($s) => Schema::minLength($s, 3))
            ->pipe(fn($s) => Schema::maxLength($s, 50))
            ->pipe(fn($s) => Schema::pattern($s, '/^[a-zA-Z]+$/'))
            ->annotate('description', 'A name field');

        $result = $this->compiler->compile($constrainedSchema->getAST());
        
        expect($result)->toHaveKey('type', 'string');
        expect($result)->toHaveKey('minLength', 3);
        expect($result)->toHaveKey('maxLength', 50);
        expect($result)->toHaveKey('pattern', '/^[a-zA-Z]+$/');
        expect($result)->toHaveKey('description', 'A name field');
    });

    it('compiles complex nested schemas for LLM integration', function () {
        $userSchema = Schema::object([
            'id' => Schema::number()
                ->annotate('description', 'Unique user identifier'),
            'profile' => Schema::object([
                'firstName' => Schema::string()
                    ->pipe(fn($s) => Schema::minLength($s, 1))
                    ->pipe(fn($s) => Schema::maxLength($s, 50)),
                'lastName' => Schema::string()
                    ->pipe(fn($s) => Schema::minLength($s, 1))
                    ->pipe(fn($s) => Schema::maxLength($s, 50)),
                'email' => Schema::string()
                    ->pipe(fn($s) => Schema::email($s))
                    ->annotate('format', 'email'),
                'age' => Schema::number()
                    ->pipe(fn($s) => Schema::min($s, 0))
                    ->pipe(fn($s) => Schema::max($s, 120))
                    ->optional(),
            ], ['firstName', 'lastName', 'email']),
            'roles' => Schema::array(Schema::union([
                Schema::literal('admin'),
                Schema::literal('user'),
                Schema::literal('guest'),
            ])),
            'settings' => Schema::object([
                'theme' => Schema::union([
                    Schema::literal('light'),
                    Schema::literal('dark'),
                ]),
                'notifications' => Schema::boolean(),
            ], ['theme', 'notifications'])->optional(),
        ], ['id', 'profile', 'roles']);

        $result = $this->compiler->compile($userSchema->getAST());
        
        // Verify overall structure
        expect($result['type'])->toBe('object');
        expect($result['required'])->toBe(['id', 'profile', 'roles']);
        
        // Verify nested profile object
        expect($result['properties']['profile']['type'])->toBe('object');
        expect($result['properties']['profile']['required'])->toBe(['firstName', 'lastName', 'email']);
        
        // Verify email format annotation
        expect($result['properties']['profile']['properties']['email']['format'])->toBe('email');
        
        // Verify roles array with union literals
        expect($result['properties']['roles']['type'])->toBe('array');
        expect($result['properties']['roles']['items']['oneOf'])->toHaveCount(3);
        
        // Verify optional settings
        expect($result['properties']['settings']['type'])->toBe('object');
    });

    it('compiles refinement schemas with descriptive information', function () {
        $refinedSchema = Schema::string()
            ->pipe(fn($s) => Schema::refine(
                $s,
                fn($value) => strlen($value) >= 8 && preg_match('/[A-Z]/', $value),
                'strong-password'
            ));

        $result = $this->compiler->compile($refinedSchema->getAST());
        
        expect($result['type'])->toBe('string');
        expect($result['description'])->toContain('strong-password');
    });

    it('generates JSON Schema suitable for LLM structured output', function () {
        // This represents a schema that could be sent to an LLM for structured generation
        $taskSchema = Schema::object([
            'task' => Schema::object([
                'title' => Schema::string()
                    ->pipe(fn($s) => Schema::minLength($s, 5))
                    ->pipe(fn($s) => Schema::maxLength($s, 100))
                    ->annotate('description', 'Clear, concise task title'),
                'description' => Schema::string()
                    ->pipe(fn($s) => Schema::minLength($s, 10))
                    ->pipe(fn($s) => Schema::maxLength($s, 500))
                    ->annotate('description', 'Detailed task description'),
                'priority' => Schema::union([
                    Schema::literal('low'),
                    Schema::literal('medium'),
                    Schema::literal('high'),
                    Schema::literal('urgent'),
                ])->annotate('description', 'Task priority level'),
                'dueDate' => Schema::string()
                    ->annotate('format', 'date')
                    ->annotate('description', 'Due date in YYYY-MM-DD format')
                    ->optional(),
                'tags' => Schema::array(Schema::string())
                    ->annotate('description', 'Relevant tags for categorization'),
            ], ['title', 'description', 'priority', 'tags']),
        ], ['task']);

        $jsonSchema = $this->compiler->compile($taskSchema->getAST());
        
        // Verify it's a complete, valid JSON Schema
        expect($jsonSchema)->toHaveKey('type', 'object');
        expect($jsonSchema)->toHaveKey('properties');
        expect($jsonSchema)->toHaveKey('required');
        
        // Verify descriptions are included for LLM guidance
        expect($jsonSchema['properties']['task']['properties']['title']['description'])->toBe('Clear, concise task title');
        expect($jsonSchema['properties']['task']['properties']['priority']['description'])->toBe('Task priority level');
        
        // Verify the schema can be JSON-encoded (no circular references)
        $jsonString = json_encode($jsonSchema);
        expect($jsonString)->toBeString();
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });
});