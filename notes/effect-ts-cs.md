# EffectTS Schema API Cheatsheet

## Primitives
```typescript
Schema.String
Schema.Number
Schema.Boolean
Schema.BigIntFromSelf
Schema.SymbolFromSelf
Schema.Object
Schema.Undefined
Schema.Void
Schema.Any
Schema.Unknown
Schema.Never
```

## Literals & Unions
```typescript
Schema.Literal("value")
Schema.Literal("a", "b", "c")
Schema.Union(schema1, schema2)
Schema.NullOr(schema)
Schema.NullishOr(schema)
Schema.UndefinedOr(schema)
Schema.Enums(enumObject)
```

## Objects & Collections
```typescript
Schema.Struct({ name: Schema.String, age: Schema.Number })
Schema.Record({ key: Schema.String, value: Schema.Number })
Schema.Array(Schema.String)
Schema.NonEmptyArray(Schema.String)
Schema.Tuple(Schema.String, Schema.Number)
```

## String Filters
```typescript
Schema.String.pipe(
  Schema.maxLength(10),
  Schema.minLength(1),
  Schema.pattern(/regex/),
  Schema.startsWith("prefix"),
  Schema.endsWith("suffix"),
  Schema.trimmed(),
  Schema.lowercased(),
  Schema.uppercased()
)
```

## Number Filters
```typescript
Schema.Number.pipe(
  Schema.greaterThan(0),
  Schema.lessThan(100),
  Schema.between(0, 100),
  Schema.int(),
  Schema.positive(),
  Schema.nonNegative()
)
```

## Array Filters
```typescript
Schema.Array(Schema.String).pipe(
  Schema.maxItems(10),
  Schema.minItems(1),
  Schema.itemsCount(5)
)
```

## Transformations
```typescript
// Basic transform
Schema.transform(fromSchema, toSchema, {
  decode: (input) => output,
  encode: (output) => input
})

// Transform with validation
Schema.transformOrFail(fromSchema, toSchema, {
  decode: (input, options, ast) => ParseResult.succeed(output),
  encode: (output, options, ast) => ParseResult.succeed(input)
})
```

## Built-in Transformations
```typescript
// String
Schema.split(",")
Schema.trim()
Schema.lowercase()
Schema.uppercase()
Schema.parseJson()
Schema.StringFromBase64
Schema.StringFromHex

// Number
Schema.NumberFromString
Schema.clamp(min, max)

// Date
Schema.Date  // from string
```

## Custom Filters
```typescript
Schema.String.pipe(
  Schema.filter((value) => value.includes("@") || "Must contain @")
)
```

## Classes
```typescript
class Person extends Schema.Class<Person>("Person")({
  name: Schema.String,
  age: Schema.Number
}) {
  greet() { return `Hello ${this.name}` }
}
```

## Validation
```typescript
// Decode (parse)
Schema.decodeUnknownSync(schema)(input)
Schema.decodeUnknownEither(schema)(input)

// Encode
Schema.encodeSync(schema)(value)
Schema.encodeEither(schema)(value)

// Validate
Schema.is(schema)(value)  // boolean
Schema.asserts(schema)(value)  // throws
```

## Default Values
```typescript
Schema.Struct({
  name: Schema.String,
  age: Schema.Number.pipe(Schema.withConstructorDefault(() => 0))
})
```

## Optionals
```typescript
Schema.Struct({
  name: Schema.String,
  age: Schema.optional(Schema.Number),
  email: Schema.optionalWith(Schema.String, { default: () => "" })
})
```

## Brands
```typescript
const UserId = Schema.Number.pipe(Schema.brand("UserId"))
type UserId = Schema.Schema.Type<typeof UserId>
```