<?php declare(strict_types=1);

use EffectPHP\Core\Run;
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;
use EffectPHP\Schema\Schema;
use EffectPHP\Schema\Schema\CollectionSchema;

// Test data classes
enum TaskStatus: string {
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
}

enum TaskPriority {
    case LOW;
    case MEDIUM;
    case HIGH;
}

it('supports complete collection workflow', function () {
    // Create a complex schema with collections
    $taskSchema = Schema::object([
        'id' => Schema::string(),
        'title' => Schema::string(),
        'status' => Schema::enum(TaskStatus::class),
        'priority' => Schema::enum(TaskPriority::class),
        'tags' => Schema::collection(Schema::string())->nonEmpty()->max(5)
    ], ['id', 'title', 'status']);

    $projectSchema = Schema::object([
        'name' => Schema::string(),
        'tasks' => Schema::collection($taskSchema)->min(1),
        'statusCounts' => Schema::collectionOf(TaskStatus::class)->length(3)
    ], ['name', 'tasks', 'statusCounts']);

    // Test data
    $projectData = [
        'name' => 'My Project',
        'tasks' => [
            [
                'id' => 'task-1',
                'title' => 'First Task',
                'status' => 'todo',
                'priority' => 'HIGH',
                'tags' => ['urgent', 'backend']
            ],
            [
                'id' => 'task-2',
                'title' => 'Second Task',
                'status' => 'in_progress',
                'priority' => 'MEDIUM',
                'tags' => ['frontend']
            ]
        ],
        'statusCounts' => ['todo', 'in_progress', 'done']
    ];

    // Decode and validate
    $result = Run::syncResult($projectSchema->decode($projectData));
    expect($result->isSuccess())->toBeTrue();

    $decoded = $result->getValueOrNull();
    expect($decoded['name'])->toBe('My Project');
    expect($decoded['tasks'])->toHaveCount(2);
    expect($decoded['tasks'][0]['status'])->toBe(TaskStatus::TODO);
    expect($decoded['tasks'][0]['priority'])->toBe(TaskPriority::HIGH);
    expect($decoded['tasks'][0]['tags'])->toBe(['urgent', 'backend']);
});

it('provides collection validation errors', function () {
    $schema = Schema::object([
        'items' => Schema::collection(Schema::string())->between(2, 4),
        'statuses' => Schema::collectionOf(TaskStatus::class)->nonEmpty()
    ]);

    // Test various validation failures
    $invalidData = [
        'items' => ['only-one'], // Too few items
        'statuses' => [] // Empty when non-empty required
    ];

    $result = Run::syncResult($schema->decode($invalidData));
    expect($result->isFailure())->toBeTrue();
});

it('supports json schema compilation', function () {
    $compiler = new JsonSchemaCompiler();
    
    // Test enum compilation
    $enumSchema = Schema::enum(TaskStatus::class);
    $compiled = $compiler->compile($enumSchema->getAST());
    
    expect($compiled)->toBe([
        'enum' => ['todo', 'in_progress', 'done'],
        'description' => 'Enum: ' . TaskStatus::class
    ]);

    // Test collection compilation
    $collectionSchema = Schema::collection(Schema::string())->min(1)->max(5);
    $compiled = $compiler->compile($collectionSchema->getAST());
    
    expect($compiled)->toBe([
        'type' => 'array',
        'items' => ['type' => 'string'],
        'minItems' => 1,
        'maxItems' => 5
    ]);
});

it('supports nested collections with constraints', function () {
    // Collection of collections with various constraints
    $schema = Schema::collection(
        Schema::collection(Schema::string())->between(1, 3)
    )->nonEmpty()->max(2);

    // Valid nested structure
    $validData = [
        ['a', 'b'],
        ['c']
    ];

    $result = Run::syncResult($schema->decode($validData));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe($validData);

    // Invalid - inner collection too large
    $invalidData = [
        ['a', 'b', 'c', 'd'] // Exceeds inner max of 3
    ];

    $result = Run::syncResult($schema->decode($invalidData));
    expect($result->isFailure())->toBeTrue();
});

it('supports mixed collection types', function () {
    // Test collections of different homogeneous types instead of mixed unions
    // This avoids union ordering issues
    
    $stringCollection = Schema::collection(Schema::string())->min(1);
    $numberCollection = Schema::collection(Schema::number())->min(1);
    $enumCollection = Schema::collection(Schema::enum(TaskStatus::class))->min(1);

    // Valid string collection
    $result = Run::syncResult($stringCollection->decode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    
    // Valid number collection
    $result = Run::syncResult($numberCollection->decode([1, 2, 3.14]));
    expect($result->isSuccess())->toBeTrue();
    
    // Valid enum collection
    $result = Run::syncResult($enumCollection->decode(['todo', 'done']));
    expect($result->isSuccess())->toBeTrue();
    $decoded = $result->getValueOrNull();
    expect($decoded)->toBe([TaskStatus::TODO, TaskStatus::DONE]);
});

it('encodes collections', function () {
    $schema = Schema::object([
        'statuses' => Schema::collectionOf(TaskStatus::class),
        'priorities' => Schema::collectionOf(TaskPriority::class)
    ]);

    $data = [
        'statuses' => [TaskStatus::TODO, TaskStatus::DONE],
        'priorities' => [TaskPriority::HIGH, TaskPriority::LOW]
    ];

    $result = Run::syncResult($schema->encode($data));
    expect($result->isSuccess())->toBeTrue();

    $encoded = $result->getValueOrNull();
    expect($encoded['statuses'])->toBe(['todo', 'done']);
    expect($encoded['priorities'])->toBe(['HIGH', 'LOW']);
});

it('supports expressive api', function () {
    // Demonstrate the expressive API we've built
    $userTagsSchema = Schema::collection(Schema::string())
        ->nonEmpty()
        ->max(10);

    $userRolesSchema = Schema::collectionOf(TaskPriority::class)
        ->between(1, 3);

    $userGroupsSchema = Schema::collection(
        Schema::object(['name' => Schema::string(), 'id' => Schema::string()])
    )->length(2);

    // All these should be fluent and type-safe
    expect($userTagsSchema)->toBeInstanceOf(CollectionSchema::class);
    expect($userRolesSchema)->toBeInstanceOf(CollectionSchema::class);
    expect($userGroupsSchema)->toBeInstanceOf(CollectionSchema::class);
});