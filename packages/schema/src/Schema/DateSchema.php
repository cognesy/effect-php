<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use DateTime;
use DateTimeInterface;
use Exception;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\DateType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Date schema implementation for date-only values (Y-m-d format)
 * Transforms between string and DateTime objects
 * 
 * @extends BaseSchema<DateTime, string>
 */
final class DateSchema extends BaseSchema
{
    public function __construct(array $annotations = [])
    {
        parent::__construct(new DateType($annotations));
    }

    /**
     * Decode string to DateTime object (date only)
     * 
     * @param mixed $input
     * @return Effect<never, \Throwable, DateTime>
     */
    public function decode(mixed $input): Effect
    {
        if (!is_string($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('string', $input, [], 'Expected string for date parsing')
            ]));
        }

        // Validate date format (Y-m-d)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('date string', $input, [], 'Expected date in Y-m-d format')
            ]));
        }

        try {
            $date = new DateTime($input);
            return Eff::succeed($date);
        } catch (Exception $e) {
            return Eff::fail(new ParseError([
                new TypeIssue('date', $input, [], "Invalid date: {$e->getMessage()}")
            ]));
        }
    }

    /**
     * Encode DateTime to string (Y-m-d format)
     * 
     * @param mixed $input
     * @return Effect<never, \Throwable, string>
     */
    public function encode(mixed $input): Effect
    {
        if (!$input instanceof DateTimeInterface) {
            return Eff::fail(new ParseError([
                new TypeIssue('DateTime', $input, [], 'Expected DateTime instance for encoding')
            ]));
        }

        return Eff::succeed($input->format('Y-m-d'));
    }

    public function annotate(string $key, mixed $value): SchemaInterface
    {
        return new DateSchema(
            array_merge($this->ast->getAnnotations(), [$key => $value])
        );
    }
}