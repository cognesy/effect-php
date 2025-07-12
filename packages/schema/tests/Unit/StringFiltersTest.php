<?php declare(strict_types=1);

use EffectPHP\Schema\Codec;
use EffectPHP\Schema\Schema;

describe('String Filter Methods', function () {
    
    describe('nonEmptyString', function () {
        it('accepts non-empty strings', function () {
            $schema = Schema::nonEmptyString();
            
            expect($schema->is("hello"))->toBeTrue();
            expect($schema->is("a"))->toBeTrue();
            expect($schema->is(" "))->toBeTrue(); // space is not empty
        });

        it('rejects empty strings', function () {
            $schema = Schema::nonEmptyString();
            
            expect($schema->is(""))->toBeFalse();
        });

        it('rejects non-strings', function () {
            $schema = Schema::nonEmptyString();
            
            expect($schema->is(123))->toBeFalse();
            expect($schema->is(null))->toBeFalse();
            expect($schema->is([]))->toBeFalse();
        });
    });

    describe('startsWith', function () {
        it('accepts strings that start with the prefix', function () {
            $schema = Schema::startsWith(Schema::string(), "Hello");
            
            expect($schema->is("Hello World"))->toBeTrue();
            expect($schema->is("Hello"))->toBeTrue();
            expect($schema->is("HelloFriends"))->toBeTrue();
        });

        it('rejects strings that do not start with the prefix', function () {
            $schema = Schema::startsWith(Schema::string(), "Hello");
            
            expect($schema->is("Hi World"))->toBeFalse();
            expect($schema->is("HELLO"))->toBeFalse(); // case sensitive
            expect($schema->is(""))->toBeFalse();
        });

        it('rejects non-strings', function () {
            $schema = Schema::startsWith(Schema::string(), "Hello");
            
            expect($schema->is(123))->toBeFalse();
            expect($schema->is(null))->toBeFalse();
        });

        it('works with empty prefix', function () {
            $schema = Schema::startsWith(Schema::string(), "");
            
            expect($schema->is("anything"))->toBeTrue();
            expect($schema->is(""))->toBeTrue();
        });
    });

    describe('endsWith', function () {
        it('accepts strings that end with the suffix', function () {
            $schema = Schema::endsWith(Schema::string(), ".com");
            
            expect($schema->is("example.com"))->toBeTrue();
            expect($schema->is("test.com"))->toBeTrue();
            expect($schema->is(".com"))->toBeTrue();
        });

        it('rejects strings that do not end with the suffix', function () {
            $schema = Schema::endsWith(Schema::string(), ".com");
            
            expect($schema->is("example.org"))->toBeFalse();
            expect($schema->is("example.COM"))->toBeFalse(); // case sensitive
            expect($schema->is(""))->toBeFalse();
        });

        it('rejects non-strings', function () {
            $schema = Schema::endsWith(Schema::string(), ".com");
            
            expect($schema->is(123))->toBeFalse();
            expect($schema->is(null))->toBeFalse();
        });

        it('works with empty suffix', function () {
            $schema = Schema::endsWith(Schema::string(), "");
            
            expect($schema->is("anything"))->toBeTrue();
            expect($schema->is(""))->toBeTrue();
        });
    });

    describe('trimmed', function () {
        it('accepts strings with no leading/trailing whitespace', function () {
            $schema = Schema::trimmed(Schema::string());
            
            expect($schema->is("hello"))->toBeTrue();
            expect($schema->is("no spaces"))->toBeTrue();
            expect($schema->is(""))->toBeTrue(); // empty string is trimmed
        });

        it('rejects strings with leading whitespace', function () {
            $schema = Schema::trimmed(Schema::string());
            
            expect($schema->is(" leading"))->toBeFalse();
            expect($schema->is("\tleading"))->toBeFalse();
            expect($schema->is("\nleading"))->toBeFalse();
        });

        it('rejects strings with trailing whitespace', function () {
            $schema = Schema::trimmed(Schema::string());
            
            expect($schema->is("trailing "))->toBeFalse();
            expect($schema->is("trailing\t"))->toBeFalse();
            expect($schema->is("trailing\n"))->toBeFalse();
        });

        it('rejects strings with both leading and trailing whitespace', function () {
            $schema = Schema::trimmed(Schema::string());
            
            expect($schema->is(" both "))->toBeFalse();
            expect($schema->is("\tboth\t"))->toBeFalse();
            expect($schema->is("\nboth\n"))->toBeFalse();
        });

        it('rejects non-strings', function () {
            $schema = Schema::trimmed(Schema::string());
            
            expect($schema->is(123))->toBeFalse();
            expect($schema->is(null))->toBeFalse();
        });
    });

    describe('combination of filters', function () {
        it('allows chaining multiple string filters', function () {
            $schema = Schema::string()
                ->pipe(fn($s) => Schema::startsWith($s, "api_"))
                ->pipe(fn($s) => Schema::endsWith($s, "_key"))
                ->pipe(fn($s) => Schema::minLength($s, 10))
                ->pipe(fn($s) => Schema::trimmed($s));
            
            expect($schema->is("api_secret_key"))->toBeTrue();
            expect($schema->is("api_test_key"))->toBeTrue();
            expect($schema->is("api_production_key"))->toBeTrue();
            
            expect($schema->is("api_key"))->toBeFalse(); // too short
            expect($schema->is("secret_key"))->toBeFalse(); // doesn't start with api_
            expect($schema->is("api_secret"))->toBeFalse(); // doesn't end with _key
            expect($schema->is(" api_secret_key"))->toBeFalse(); // not trimmed
            expect($schema->is("api_secret_key "))->toBeFalse(); // not trimmed
        });

        it('works with nonEmptyString in chains', function () {
            $schema = Schema::nonEmptyString()
                ->pipe(fn($s) => Schema::startsWith($s, "test_"))
                ->pipe(fn($s) => Schema::trimmed($s));
            
            expect($schema->is("test_value"))->toBeTrue();
            expect($schema->is("test_"))->toBeTrue();
            
            expect($schema->is(""))->toBeFalse(); // empty
            expect($schema->is("other_value"))->toBeFalse(); // wrong prefix
            expect($schema->is(" test_value"))->toBeFalse(); // not trimmed
        });
    });

    describe('integration with helper methods', function () {
        it('works with decodeUnknownEither', function () {
            $schema = Schema::startsWith(Schema::string(), "hello");
            $decoder = Codec::decodeUnknownResult($schema);
            
            $validResult = $decoder("hello world");
            expect($validResult->isSuccess())->toBeTrue();
            $value = $validResult->fold(fn($e) => null, fn($v) => $v);
            expect($value)->toBe("hello world");
            
            $invalidResult = $decoder("goodbye world");
            expect($invalidResult->isFailure())->toBeTrue();
        });

        it('works with encodeEither', function () {
            $schema = Schema::trimmed(Schema::string());
            $encoder = Codec::encodeResult($schema);
            
            $validResult = $encoder("no spaces");
            expect($validResult->isSuccess())->toBeTrue();
            
            $invalidResult = $encoder(" has spaces ");
            expect($invalidResult->isFailure())->toBeTrue();
        });
    });

    describe('real-world scenarios', function () {
        it('validates API keys with specific format', function () {
            $schema = Schema::string()
                ->pipe(fn($s) => Schema::startsWith($s, "sk_"))
                ->pipe(fn($s) => Schema::minLength($s, 20))
                ->pipe(fn($s) => Schema::trimmed($s));
            
            expect($schema->is("sk_test_1234567890abcdef"))->toBeTrue();
            expect($schema->is("sk_live_abcdef1234567890"))->toBeTrue();
            
            expect($schema->is("pk_test_1234567890"))->toBeFalse(); // wrong prefix
            expect($schema->is("sk_short"))->toBeFalse(); // too short
            expect($schema->is(" sk_test_1234567890abcdef"))->toBeFalse(); // not trimmed
        });

        it('validates email domains', function () {
            $schema = Schema::string()
                ->pipe(fn($s) => Schema::email($s))
                ->pipe(fn($s) => Schema::endsWith($s, "@company.com"));
            
            expect($schema->is("john@company.com"))->toBeTrue();
            expect($schema->is("jane.doe@company.com"))->toBeTrue();
            
            expect($schema->is("john@other.com"))->toBeFalse(); // wrong domain
            expect($schema->is("invalid-email"))->toBeFalse(); // not email
        });

        it('validates trimmed non-empty usernames', function () {
            $schema = Schema::nonEmptyString()
                ->pipe(fn($s) => Schema::trimmed($s))
                ->pipe(fn($s) => Schema::minLength($s, 3))
                ->pipe(fn($s) => Schema::maxLength($s, 20));
            
            expect($schema->is("john_doe"))->toBeTrue();
            expect($schema->is("alice"))->toBeTrue();
            
            expect($schema->is(""))->toBeFalse(); // empty
            expect($schema->is("ab"))->toBeFalse(); // too short
            expect($schema->is(" john_doe"))->toBeFalse(); // not trimmed
            expect($schema->is("john_doe "))->toBeFalse(); // not trimmed
        });
    });
});