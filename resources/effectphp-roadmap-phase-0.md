## Phase 0: Foundation (Month 1)
### Developer Experience Bootstrap

**Goal**: Establish robust development environment and tooling foundation.

#### 0.1 Project Structure & Tooling
```bash
# Monorepo structure
effectphp/
├── packages/
│   ├── core/           # Module 1
│   ├── runtime/        # Module 2  
│   ├── flow/          # Module 3
│   └── ...
├── tools/
│   ├── migration/     # Legacy code migration tools
│   ├── playground/    # Interactive documentation
│   └── psalm-rules/   # Custom static analysis rules
└── docs/
    ├── getting-started/
    ├── migration-guide/
    └── api-reference/
```

#### 0.2 Static Analysis Configuration
```php
<?php // psalm.xml configuration
<psalm 
    level="9"
    errorLevel="error"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
    
    <issueHandlers>
        <MixedReturnStatement errorLevel="error" />
        <MixedInferredReturnType errorLevel="error" />
    </issueHandlers>
    
    <plugins>
        <pluginClass class="EffectPHP\Psalm\Plugin\EffectPlugin" />
    </plugins>
</psalm>
```

#### 0.3 Custom Psalm Rules
```php
<?php
// Custom validation for Effect type safety
final class EffectTypeChecker extends \Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return ['EffectPHP\Effect'];
    }
    
    public static function getMethodReturnType(
        \Psalm\StatementsSource $source,
        string $fq_classlike_name,
        string $method_name_lowercase,
        array $call_args,
        \Psalm\Context $context,
        \Psalm\CodeLocation $code_location,
        ?array $template_type_parameters = null
    ): ?\Psalm\Type\Union {
        // Validate Effect composition type safety
        if ($method_name_lowercase === 'flatmap') {
            return $this->validateFlatMapTypes($call_args, $template_type_parameters);
        }
        return null;
    }
}
```

**Dependencies**: None  
**Success Criteria**: 
- Psalm level 9 passes on empty project
- Custom rules validate Effect patterns
- Migration assistant CLI tool functional
