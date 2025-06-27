<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\ASTNodeInterface;

/**
 * Base schema implementation following EffectTS patterns
 * 
 * @template A The type this schema validates/produces
 * @template I The input type this schema accepts
 */
abstract class BaseSchema implements SchemaInterface
{
    protected ASTNodeInterface $ast;

    public function __construct(ASTNodeInterface $ast)
    {
        $this->ast = $ast;
    }

    public function getAST(): ASTNodeInterface
    {
        return $this->ast;
    }

    /**
     * @param I $input
     * @return Effect<never, \Throwable, A>
     */
    abstract public function decode(mixed $input): Effect;

    /**
     * @param A $input
     * @return Effect<never, \Throwable, I>
     */
    abstract public function encode(mixed $input): Effect;

    /**
     * Test if input is valid - materializes Effect at edge
     * 
     * @param I $input
     */
    public function is(mixed $input): bool
    {
        // Materialize Effect to Either using runtime
        $result = Eff::runSafely($this->decode($input));
        return $result->isRight();
    }

    /**
     * Assert input is valid - materializes Effect at edge
     * 
     * @param I $input
     * @return A
     * @throws \Throwable
     */
    public function assert(mixed $input): mixed
    {
        // Materialize Effect with runtime, throw on failure
        return Eff::runSync($this->decode($input));
    }

    /**
     * Transform this schema through a function
     * 
     * @param callable(SchemaInterface): SchemaInterface $transform
     */
    public function pipe(callable $transform): SchemaInterface
    {
        return $transform($this);
    }

    /**
     * Make this schema optional (allows null)
     */
    public function optional(): SchemaInterface
    {
        return new OptionalSchema($this);
    }

    /**
     * Make this schema nullable (explicitly allows null)
     */
    public function nullable(): SchemaInterface
    {
        return new NullableSchema($this);
    }

    /**
     * Add metadata annotation to schema
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new static($this->ast->withAnnotations([$key => $value]));
    }

    /**
     * Compose this schema with another
     */
    public function compose(SchemaInterface $other): SchemaInterface
    {
        return new CompositeSchema($this, $other);
    }
}
