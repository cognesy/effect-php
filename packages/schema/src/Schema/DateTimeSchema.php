<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use DateTime;
use DateTimeInterface;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\DateTimeType;
use EffectPHP\Schema\Contracts\SchemaInterface;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;
use Exception;

/**
 * DateTime schema implementation for date and time values (ISO 8601 format)
 * Transforms between string and DateTime objects
 *
 * @extends BaseSchema<DateTime, string>
 */
final class DateTimeSchema extends BaseSchema
{
    public function __construct(array $annotations = []) {
        parent::__construct(new DateTimeType($annotations));
    }

    /**
     * Decode string to DateTime object (with time)
     *
     * @param mixed $input
     * @return Effect<never, \Throwable, DateTime>
     */
    public function decode(mixed $input): Effect {
        if (!is_string($input)) {
            return Eff::fail(new ParseError([
                new TypeIssue('string', $input, [], 'Expected string for datetime parsing'),
            ]));
        }

        // Support multiple datetime formats
        $formats = [
            'Y-m-d H:i:s',           // 2023-12-25 15:30:00
            'Y-m-d\TH:i:s',          // 2023-12-25T15:30:00
            'Y-m-d\TH:i:s\Z',        // 2023-12-25T15:30:00Z
            'Y-m-d\TH:i:sP',         // 2023-12-25T15:30:00+00:00
            DateTime::ATOM,          // Full ISO 8601
            DateTime::ISO8601,       // ISO 8601 variant
        ];

        $lastException = null;

        foreach ($formats as $format) {
            try {
                $date = DateTime::createFromFormat($format, $input);
                if ($date !== false) {
                    return Eff::succeed($date);
                }
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        // Try general DateTime constructor as fallback
        try {
            $date = new DateTime($input);
            return Eff::succeed($date);
        } catch (Exception $e) {
            $message = $lastException ? $lastException->getMessage() : $e->getMessage();
            return Eff::fail(new ParseError([
                new TypeIssue('datetime', $input, [], "Invalid datetime: {$message}"),
            ]));
        }
    }

    /**
     * Encode DateTime to ISO 8601 string
     *
     * @param mixed $input
     * @return Effect<never, \Throwable, string>
     */
    public function encode(mixed $input): Effect {
        if (!$input instanceof DateTimeInterface) {
            return Eff::fail(new ParseError([
                new TypeIssue('DateTime', $input, [], 'Expected DateTime instance for encoding'),
            ]));
        }

        return Eff::succeed($input->format(DateTime::ATOM));
    }

    public function annotate(string $key, mixed $value): SchemaInterface {
        return new DateTimeSchema(
            array_merge($this->ast->getAnnotations(), [$key => $value]),
        );
    }
}