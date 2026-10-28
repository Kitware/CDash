<?php

namespace Tests\Unit\GraphQL\Directives;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use PHPUnit\Framework\Attributes\DataProvider;

class FilterDirectiveTest extends DirectiveTestCase
{
    /**
     * A schema with a filterable connection, a filterable count, and filterable relationships in both directions.
     */
    private const SCHEMA = /* @lang GraphQL */ '
        type Query {
            projects(filters: _ @filter): [Project!]! @paginate(type: CONNECTION)
        }

        type Project {
            id: ID! @filterable
            name: String! @filterable
            visibility: Int! @rename(attribute: "public") @filterable
            builds(filters: _ @filter): [Build!]! @hasMany(type: CONNECTION)
            buildCount(filters: _ @filter(inputType: "BuildFilterInput")): Int! @count(relation: "builds")
        }

        type Build {
            id: ID! @filterable
            name: String! @filterable
            project: Project! @belongsTo
            site: Site! @belongsTo
            children(filters: _ @filter): [Build!]! @hasMany(type: CONNECTION)
        }

        type Site {
            id: ID!
        }
    ';

    /**
     * @return array<string,string> The relationship filter input's fields, sorted by name.  Their order isn't
     *                              meaningful, and Lighthouse moves paginated relationships to the end of the type.
     */
    private static function relationshipFilters(DocumentAST $documentAST, string $typeName): array
    {
        $fields = self::inputFields($documentAST, $typeName);
        ksort($fields);
        return $fields;
    }

    public function testReplacesArgumentTypeWithMultiFilterInput(): void
    {
        $documentAST = $this->buildValidSchema(self::SCHEMA);

        self::assertSame('QueryProjectsFiltersMultiFilterInput @filter', self::fieldArguments($documentAST, 'Query', 'projects')['filters']);
        self::assertSame('ProjectBuildsFiltersMultiFilterInput @filter', self::fieldArguments($documentAST, 'Project', 'builds')['filters']);
    }

    public function testGeneratesMultiFilterInput(): void
    {
        $documentAST = $this->buildValidSchema(self::SCHEMA);

        // Each filter object uses exactly one operator.
        self::assertSame([
            'any' => '[QueryProjectsFiltersMultiFilterInput] @rules(apply: ["prohibits:all,has,eq,ne,gt,lt,ge,le,contains"])',
            'all' => '[QueryProjectsFiltersMultiFilterInput] @rules(apply: ["prohibits:any,has,eq,ne,gt,lt,ge,le,contains"])',
            'has' => 'ProjectRelationshipFilterInput @rules(apply: ["prohibits:any,all,eq,ne,gt,lt,ge,le,contains"])',
            'eq' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,ne,gt,lt,ge,le,contains"])',
            'ne' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,eq,gt,lt,ge,le,contains"])',
            'gt' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,eq,ne,lt,ge,le,contains"])',
            'lt' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,eq,ne,gt,ge,le,contains"])',
            'ge' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,eq,ne,gt,lt,le,contains"])',
            'le' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,eq,ne,gt,lt,ge,contains"])',
            'contains' => 'ProjectFilterInput @rules(apply: ["prohibits:any,all,has,eq,ne,gt,lt,ge,le"])',
        ], self::inputFields($documentAST, 'QueryProjectsFiltersMultiFilterInput'));
    }

    public function testInputTypeArgumentOverridesFilterInput(): void
    {
        $documentAST = $this->buildValidSchema(self::SCHEMA);

        self::assertSame(
            'BuildFilterInput @rules(apply: ["prohibits:any,all,has,ne,gt,lt,ge,le,contains"])',
            self::inputFields($documentAST, 'ProjectBuildCountFiltersMultiFilterInput')['eq'],
        );
    }

    public function testGeneratesRelationshipFilterInputs(): void
    {
        $documentAST = $this->buildValidSchema(self::SCHEMA);

        // buildCount is filterable, but it isn't a connection.
        self::assertSame([
            'builds' => 'ProjectBuildsFiltersMultiFilterInput',
        ], self::relationshipFilters($documentAST, 'ProjectRelationshipFilterInput'));
        self::assertStringStartsWith('BuildRelationshipFilterInput ', self::inputFields($documentAST, 'ProjectBuildsFiltersMultiFilterInput')['has']);

        // Site has nothing to filter on.
        self::assertSame([
            'children' => 'BuildChildrenFiltersMultiFilterInput',
            'project' => 'ProjectMultiFilterInput',
        ], self::relationshipFilters($documentAST, 'BuildRelationshipFilterInput'));

        // Single record relationships use a filter input generated for the related type.
        $projectFilter = self::inputFields($documentAST, 'ProjectMultiFilterInput');
        self::assertStringStartsWith('ProjectRelationshipFilterInput ', $projectFilter['has']);
        self::assertStringStartsWith('ProjectFilterInput ', $projectFilter['eq']);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function singleRecordRelationshipDirectives(): array
    {
        return [
            'belongsTo' => ['belongsTo'],
            'hasOne' => ['hasOne'],
            'hasOneThrough' => ['hasOneThrough'],
            'morphOne' => ['morphOne'],
            'morphTo' => ['morphTo'],
        ];
    }

    #[DataProvider('singleRecordRelationshipDirectives')]
    public function testFiltersSingleRecordRelationships(string $directive): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ "
            type Query {
                builds(filters: _ @filter): [Build!]! @paginate(type: CONNECTION)
            }

            type Build {
                id: ID! @filterable
                project: Project! @{$directive}
                children(filters: _ @filter): [Build!]! @hasMany(type: CONNECTION)
            }

            type Project {
                id: ID! @filterable
            }
        ");

        self::assertSame([
            'children' => 'BuildChildrenFiltersMultiFilterInput',
            'project' => 'ProjectMultiFilterInput',
        ], self::relationshipFilters($documentAST, 'BuildRelationshipFilterInput'));
        self::assertStringStartsWith('ProjectFilterInput ', self::inputFields($documentAST, 'ProjectMultiFilterInput')['eq']);
    }

    public function testOmitsHasWithoutFilterableRelationships(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects(filters: _ @filter): [Project!]! @paginate(type: CONNECTION)
            }

            type Project {
                id: ID! @filterable
                name: String! @filterable
            }
        ');

        self::assertArrayNotHasKey('has', self::inputFields($documentAST, 'QueryProjectsFiltersMultiFilterInput'));
        self::assertTypeNotGenerated($documentAST, 'ProjectRelationshipFilterInput');
    }

    public function testSingleValueFieldHasNoOrderBy(): void
    {
        $documentAST = $this->buildValidSchema(self::SCHEMA);

        self::assertSame(['filters'], array_keys(self::fieldArguments($documentAST, 'Project', 'buildCount')));
    }

    public function testAddsOrderByToConnection(): void
    {
        $documentAST = $this->buildValidSchema(self::SCHEMA);

        self::assertSame(
            '[QueryProjectsOrderByOrderByClause!] = [{ column: ID, order: ASC }] @orderBy(columnsEnum: "QueryProjectsOrderByOrderByColumn")',
            self::fieldArguments($documentAST, 'Query', 'projects')['orderBy'],
        );

        // Clients use the GraphQL field names, which are mapped to the underlying columns.
        self::assertSame([
            'ID @enum(value: "id")',
            'NAME @enum(value: "name")',
            'VISIBILITY @enum(value: "public")',
        ], self::enumValues($documentAST, 'QueryProjectsOrderByOrderByColumn'));
        self::assertSame(
            'QueryProjectsOrderByOrderByColumn!',
            self::inputFields($documentAST, 'QueryProjectsOrderByOrderByClause')['column'],
        );
    }

    public function testAddsOrderByToNullableListField(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects(filters: _ @filter): [Project!] @all
            }

            type Project {
                id: ID! @filterable
                name: String! @filterable
            }
        ');

        self::assertSame(
            '[QueryProjectsOrderByOrderByClause!] = [{ column: ID, order: ASC }] @orderBy(columnsEnum: "QueryProjectsOrderByOrderByColumn")',
            self::fieldArguments($documentAST, 'Query', 'projects')['orderBy'],
        );
    }

    public function testOrderByHasNoDefaultWithoutFilterableId(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                sites(filters: _ @filter): [Site!]! @paginate(type: CONNECTION)
            }

            type Site {
                id: ID!
                name: String! @filterable
            }
        ');

        self::assertSame(
            '[QuerySitesOrderByOrderByClause!] @orderBy(columnsEnum: "QuerySitesOrderByOrderByColumn")',
            self::fieldArguments($documentAST, 'Query', 'sites')['orderBy'],
        );
        self::assertSame(['NAME @enum(value: "name")'], self::enumValues($documentAST, 'QuerySitesOrderByOrderByColumn'));
    }

    public function testKeepsExistingOrderByArgument(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects(filters: _ @filter, orderBy: _ @orderBy(columns: ["name"])): [Project!]! @paginate(type: CONNECTION)
            }

            type Project {
                id: ID! @filterable
                name: String! @filterable
            }
        ');

        self::assertSame(
            '[QueryProjectsOrderByOrderByClause!] @orderBy(columns: ["name"])',
            self::fieldArguments($documentAST, 'Query', 'projects')['orderBy'],
        );
        self::assertTypeNotGenerated($documentAST, 'QueryProjectsOrderByOrderByColumn');
    }

    public function testRelationshipCyclesTerminate(): void
    {
        $documentAST = $this->buildValidSchema(/* @lang GraphQL */ '
            type Query {
                projects(filters: _ @filter): [Project!]! @paginate(type: CONNECTION)
            }

            type Project {
                id: ID! @filterable
                builds(filters: _ @filter): [Build!]! @hasMany(type: CONNECTION)
                mostRecentBuild: Build @hasOne
            }

            type Build {
                id: ID! @filterable
                project: Project! @belongsTo
                children(filters: _ @filter): [Build!]! @hasMany(type: CONNECTION)
            }
        ');

        self::assertSame([
            'builds' => 'ProjectBuildsFiltersMultiFilterInput',
            'mostRecentBuild' => 'BuildMultiFilterInput',
        ], self::relationshipFilters($documentAST, 'ProjectRelationshipFilterInput'));
        self::assertSame([
            'children' => 'BuildChildrenFiltersMultiFilterInput',
            'project' => 'ProjectMultiFilterInput',
        ], self::relationshipFilters($documentAST, 'BuildRelationshipFilterInput'));
    }
}
