<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\UnionType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\CompositeIssue;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Union schema implementation using core Effects
 *
 * @template T
 * @extends BaseSchema<T, mixed>
 */
final class UnionSchema extends BaseSchema
{
    private array $schemas;

    /**
     * @param SchemaInterface[] $schemas
     */
    public function __construct(array $schemas, array $annotations = []) {
        $this->schemas = $schemas;

        $astTypes = array_map(fn($schema) => $schema->getAST(), $schemas);
        parent::__construct(new UnionType($astTypes, $annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect {
        // Try each schema in order until one succeeds
        $effects = [];
        foreach ($this->schemas as $index => $schema) {
            $effects[$index] = $schema->decode($input);
        }

        // Use core's race functionality to return first successful validation
        return Eff::raceAll($effects)->catchError(
            fn() => true, // Catch any error
            function () use ($input) {
                // If all schemas failed, return a composite error
                return Eff::fail(new ParseError([
                    new CompositeIssue([], [], 'All union members failed validation'),
                ]));
            },
        );
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function encode(mixed $input): Effect {
        // For encoding, we need to find which schema can handle this value
        foreach ($this->schemas as $schema) {
            if ($schema->is($input)) {
                return $schema->encode($input);
            }
        }

        return Eff::fail(new ParseError([
            new TypeIssue('union', $input, [], 'No union member can encode this value'),
        ]));
    }

    /**
     * Override annotate to handle UnionSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface {
        return new UnionSchema(
            $this->schemas,
            array_merge($this->ast->getAnnotations(), [$key => $value]),
        );
    }
}
