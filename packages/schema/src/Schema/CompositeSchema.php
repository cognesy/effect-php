<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Schema\AST\ObjectType;
use EffectPHP\Schema\Contracts\SchemaInterface;

/**
 * Composite schema for schema composition using core Effects
 *
 * @template A
 * @extends BaseSchema<A, mixed>
 */
final class CompositeSchema extends BaseSchema
{
    private SchemaInterface $left;
    private SchemaInterface $right;

    public function __construct(SchemaInterface $left, SchemaInterface $right) {
        $this->left = $left;
        $this->right = $right;

        $leftAst = $left->getAST();
        $rightAst = $right->getAST();

        if ($leftAst instanceof ObjectType && $rightAst instanceof ObjectType) {
            $ast = new ObjectType(
                array_merge($leftAst->getProperties(), $rightAst->getProperties()),
                array_merge($leftAst->getRequired(), $rightAst->getRequired()),
                array_merge($leftAst->getAnnotations(), $rightAst->getAnnotations()),
            );
        } else {
            // For now, we'll just merge annotations for non-object types.
            // A more sophisticated merging strategy could be implemented here.
            $ast = $leftAst->withAnnotations($rightAst->getAnnotations());
        }

        parent::__construct($ast);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect {
        return $this->left->decode($input)->flatMap(fn($leftValue) => $this->right->decode($input)->map(fn($rightValue) => array_merge((array)$leftValue, (array)$rightValue),
        ),
        );
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function encode(mixed $input): Effect {
        return $this->left->encode($input)->flatMap(fn($leftValue) => $this->right->encode($input)->map(fn($rightValue) => array_merge((array)$leftValue, (array)$rightValue),
        ),
        );
    }
}
