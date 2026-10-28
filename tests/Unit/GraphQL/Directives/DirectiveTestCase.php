<?php

namespace Tests\Unit\GraphQL\Directives;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Printer;
use JsonException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Validator;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Tests\TestCase;
use Tests\Traits\DisablesSchemaCache;

/**
 * Tests a directive's schema manipulation against a small schema written for the test, rather than relying on
 * how the real schema happens to use the directive.
 */
abstract class DirectiveTestCase extends TestCase
{
    use DisablesSchemaCache;
    use UsesTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        // Otherwise, a cached copy of the real schema would be used instead of the test schema.
        $this->disableSchemaCache();
        $this->setUpTestSchema();
    }

    /**
     * Build the given schema and apply its directives.
     */
    protected function buildSchema(string $schema): DocumentAST
    {
        // ASTBuilder keeps the first schema it builds, so a second schema would be silently ignored.
        self::assertFalse(isset($this->schema), 'Only one schema can be built per test.');

        $this->schema = $schema;
        $documentAST = app(ASTBuilder::class)->documentAST();

        self::assertSame(
            self::queryFieldNames(DocumentAST::fromSource($schema)),
            self::queryFieldNames($documentAST),
            'The real schema was built instead of the test schema.',
        );

        return $documentAST;
    }

    /**
     * Build the given schema and validate the result, which checks that the generated types are consistent.
     */
    protected function buildValidSchema(string $schema): DocumentAST
    {
        $documentAST = $this->buildSchema($schema);
        app(Validator::class)->validate();

        return $documentAST;
    }

    protected static function assertTypeNotGenerated(DocumentAST $documentAST, string $typeName): void
    {
        self::assertFalse(isset($documentAST->types[$typeName]), "Unexpected type {$typeName}.");
    }

    /**
     * @return array<string,string> The input type's fields, each printed by printInputValue()
     */
    protected static function inputFields(DocumentAST $documentAST, string $typeName): array
    {
        $type = $documentAST->types[$typeName] ?? null;
        self::assertInstanceOf(InputObjectTypeDefinitionNode::class, $type, "Missing input type {$typeName}.");

        return self::printInputValues($type->fields);
    }

    /**
     * @return array<string,string> The field's arguments, each printed by printInputValue()
     */
    protected static function fieldArguments(DocumentAST $documentAST, string $typeName, string $fieldName): array
    {
        $type = $documentAST->types[$typeName] ?? null;
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $type, "Missing type {$typeName}.");
        $field = ASTHelper::firstByName($type->fields, $fieldName);
        self::assertNotNull($field, "Missing field {$typeName}.{$fieldName}.");

        return self::printInputValues($field->arguments);
    }

    /**
     * @return array<string> The enum's values, including their directives
     */
    protected static function enumValues(DocumentAST $documentAST, string $typeName): array
    {
        $type = $documentAST->types[$typeName] ?? null;
        self::assertInstanceOf(EnumTypeDefinitionNode::class, $type, "Missing enum {$typeName}.");

        $values = [];
        foreach ($type->values as $value) {
            $values[] = self::printNode($value);
        }
        return $values;
    }

    /**
     * @return array<string> The Query type's field names, sorted because pagination moves fields to the end
     */
    private static function queryFieldNames(DocumentAST $documentAST): array
    {
        $type = $documentAST->types['Query'] ?? null;
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $type, 'Missing type Query.');

        $names = [];
        foreach ($type->fields as $field) {
            $names[] = $field->name->value;
        }
        sort($names);
        return $names;
    }

    /**
     * @param NodeList<InputValueDefinitionNode> $inputValues
     *
     * @return array<string,string>
     */
    private static function printInputValues(NodeList $inputValues): array
    {
        $printed = [];
        foreach ($inputValues as $inputValue) {
            $printed[$inputValue->name->value] = self::printInputValue($inputValue);
        }
        return $printed;
    }

    /**
     * Print an input value's type, default value, and directives, e.g. `String! = "UTC" @rules(...)`.  The name
     * and description are left out.
     */
    private static function printInputValue(InputValueDefinitionNode $inputValue): string
    {
        $printed = self::printNode($inputValue->type);
        if ($inputValue->defaultValue !== null) {
            $printed .= ' = ' . self::printNode($inputValue->defaultValue);
        }
        foreach ($inputValue->directives as $directive) {
            $printed .= ' ' . self::printNode($directive);
        }
        return $printed;
    }

    private static function printNode(Node $node): string
    {
        try {
            return Printer::doPrint($node);
        } catch (JsonException $e) {
            // Only strings which aren't valid UTF-8 fail to print.
            self::fail("Unable to print node: {$e->getMessage()}");
        }
    }
}
