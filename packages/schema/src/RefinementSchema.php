<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\RefinementType;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\RefinementIssue;

/**
 * Refinement schema implementation using core Effects
 * 
 * @template A
 * @extends BaseSchema<A, mixed>
 */
final class RefinementSchema extends BaseSchema
{
    private SchemaInterface $inner;
    private \Closure $predicate;
    private string $name;

    public function __construct(SchemaInterface $inner, callable $predicate, string $name, array $annotations = [])
    {
        $this->inner = $inner;
        $this->predicate = $predicate;
        $this->name = $name;

        parent::__construct(new RefinementType($inner->getAST(), $predicate, $name, $annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect
    {
        // Use flatMap for Effect composition - decode then refine
        return $this->inner->decode($input)->flatMap(function ($value) {
            try {
                $result = ($this->predicate)($value);
                if (!$result) {
                    return Eff::fail(new ParseError([
                        new RefinementIssue($this->name, $value, [], "Refinement '{$this->name}' failed")
                    ]));
                }
                return Eff::succeed($value);
            } catch (\Throwable $e) {
                // Let exceptions from predicates propagate as-is for catchError to handle
                return Eff::fail($e);
            }
        });
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function encode(mixed $input): Effect
    {
        if (!($this->predicate)($input)) {
            return Eff::fail(new ParseError([
                new RefinementIssue($this->name, $input, [], "Refinement '{$this->name}' failed for encoding")
            ]));
        }
        
        return $this->inner->encode($input);
    }

    /**
     * Override annotate to handle RefinementSchema's specific constructor
     */
    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new RefinementSchema(
            $this->inner,
            $this->predicate,
            $this->name,
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}
