<?php

namespace Tests\Unit\GraphQL\Directives;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

class FilterableDirectiveTest extends DirectiveTestCase
{
    public function testGeneratesFilterInput(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects: [Project!]! @all
            }

            enum ProjectVisibility {
                PUBLIC @enum(value: 1)
                PRIVATE @enum(value: 0)
            }

            type Project {
                id: ID! @filterable
                name: String! @filterable
                description: String @filterable
                visibility: ProjectVisibility! @rename(attribute: "public") @filterable
            }
        ');

        // Every field is nullable, and only one field can be used per filter.
        self::assertSame([
            'id' => 'ID @rules(apply: ["prohibits:name,description,visibility"])',
            'name' => 'String @rules(apply: ["prohibits:id,description,visibility"])',
            'description' => 'String @rules(apply: ["prohibits:id,name,visibility"])',
            'visibility' => 'ProjectVisibility @rules(apply: ["prohibits:id,name,description"]) @rename(attribute: "public")',
        ], self::inputFields($documentAST, 'ProjectFilterInput'));
    }

    public function testOmitsFieldsWithoutFilterable(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects: [Project!]! @all
            }

            type Project {
                id: ID! @filterable
                name: String!
                description: String @filterable
            }
        ');

        self::assertSame(['id', 'description'], array_keys(self::inputFields($documentAST, 'ProjectFilterInput')));
    }

    public function testCopiesFieldDescriptions(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects: [Project!]! @all
            }

            type Project {
                "Unique primary key."
                id: ID! @filterable
                name: String! @filterable
            }
        ');

        $filterInput = $documentAST->types['ProjectFilterInput'] ?? null;
        self::assertInstanceOf(InputObjectTypeDefinitionNode::class, $filterInput, 'Missing input type ProjectFilterInput.');
        $idField = ASTHelper::firstByName($filterInput->fields, 'id');
        $nameField = ASTHelper::firstByName($filterInput->fields, 'name');
        self::assertNotNull($idField);
        self::assertNotNull($nameField);
        self::assertSame('Unique primary key.', $idField->description?->value);
        self::assertNull($nameField->description);
    }

    public function testGeneratesFilterInputForEachType(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects: [Project!]! @all
                sites: [Site!]! @all
                users: [User!]! @all
            }

            type Project {
                id: ID! @filterable
            }

            type Site {
                name: String! @filterable
            }

            type User {
                email: String!
            }
        ');

        self::assertSame(['id'], array_keys(self::inputFields($documentAST, 'ProjectFilterInput')));
        self::assertSame(['name'], array_keys(self::inputFields($documentAST, 'SiteFilterInput')));
        self::assertTypeNotGenerated($documentAST, 'UserFilterInput');
    }
}
