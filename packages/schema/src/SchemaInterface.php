<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Schema\AST\ASTNodeInterface;

/**
 * Schema interface following EffectTS patterns
 * 
 * @template A The type this schema validates/produces
 * @template I The input type this schema accepts (defaults to mixed)
 */
interface SchemaInterface
{
    /**
     * Get the AST representation of this schema
     */
    public function getAST(): ASTNodeInterface;

    /**
     * Decode input into validated type A
     * 
     * @param I $input
     * @return Effect<never, \Throwable, A>
     */
    public function decode(mixed $input): Effect;

    /**
     * Encode validated type A back to input format I
     * 
     * @param A $input
     * @return Effect<never, \Throwable, I>
     */
    public function encode(mixed $input): Effect;

    /**
     * Test if input is valid (materializes Effect at edge)
     * 
     * @param I $input
     */
    public function is(mixed $input): bool;

    /**
     * Assert input is valid, throw on failure (materializes Effect at edge)
     * 
     * @param I $input
     * @return A
     * @throws \Throwable
     */
    public function assert(mixed $input): mixed;

    /**
     * Transform this schema through a function
     * 
     * @param callable(SchemaInterface): SchemaInterface $transform
     */
    public function pipe(callable $transform): SchemaInterface;

    /**
     * Make this schema optional (allows null)
     */
    public function optional(): SchemaInterface;

    /**
     * Make this schema nullable (explicitly allows null)
     */
    public function nullable(): SchemaInterface;

    /**
     * Add metadata annotation to schema
     */
    public function annotate(string $key, mixed $value): SchemaInterface;

    /**
     * Compose this schema with another
     */
    public function compose(SchemaInterface $other): SchemaInterface;
}
