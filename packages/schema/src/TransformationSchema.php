<?php

declare(strict_types=1);

namespace EffectPHP\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\TransformationType;

/**
 * Transformation schema for bidirectional conversions using core Effects
 * 
 * @template From
 * @template To
 * @extends BaseSchema<To, From>
 */
final class TransformationSchema extends BaseSchema
{
    private SchemaInterface $from;
    private SchemaInterface $to;
    private \Closure $decode;
    private \Closure $encode;

    public function __construct(
        SchemaInterface $from,
        SchemaInterface $to,
        callable $decode,
        callable $encode,
        array $annotations = []
    ) {
        $this->from = $from;
        $this->to = $to;
        $this->decode = $decode;
        $this->encode = $encode;

        parent::__construct(new TransformationType(
            $from->getAST(),
            $to->getAST(),
            $decode,
            $encode,
            $annotations
        ));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect
    {
        // Use Effect composition: decode with 'from' schema then transform
        return $this->from->decode($input)->flatMap(function ($value) {
            try {
                $transformed = ($this->decode)($value);
                return $this->to->decode($transformed);
            } catch (\Throwable $e) {
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
        // Use Effect composition: encode with 'to' schema then transform back
        return $this->to->encode($input)->flatMap(function ($value) {
            try {
                $transformed = ($this->encode)($value);
                return $this->from->encode($transformed);
            } catch (\Throwable $e) {
                return Eff::fail($e);
            }
        });
    }
}
