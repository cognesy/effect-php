<?php

namespace EffectPHP\Schema;

use EffectPHP\Core\Run;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Utils\Result\Result;

class Codec {
    /**
     * Decode unknown input synchronously - throws on error
     *
     * @template A
     * @param SchemaInterface $schema
     * @return callable(mixed): A
     * @throws \Throwable
     */
    public static function decodeUnknownSync(SchemaInterface $schema): callable {
        return function (mixed $input) use ($schema) {
            return Run::sync($schema->decode($input));
        };
    }

    /**
     * Decode unknown input returning Either - no exceptions
     *
     * @template A
     * @param SchemaInterface $schema
     * @return callable(mixed): Result<\Throwable, A>
     */
    public static function decodeUnknownResult(SchemaInterface $schema): callable {
        return function (mixed $input) use ($schema): Result {
            return Run::syncResult($schema->decode($input));
        };
    }

    /**
     * Encode value synchronously - throws on error
     *
     * @template I
     * @param SchemaInterface $schema
     * @return callable(mixed): I
     * @throws \Throwable
     */
    public static function encodeSync(SchemaInterface $schema): callable {
        return function (mixed $value) use ($schema) {
            return Run::sync($schema->encode($value));
        };
    }

    /**
     * Encode value returning Result - no exceptions
     *
     * @template I
     * @param SchemaInterface $schema
     * @return callable(mixed): Result<\Throwable, I>
     */
    public static function encodeResult(SchemaInterface $schema): callable {
        return function (mixed $value) use ($schema): Result {
            return Run::syncResult($schema->encode($value));
        };
    }
}