<?php

namespace Tests\Unit\GraphQL\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use PHPUnit\Framework\Attributes\DataProvider;

class CountHistogramDirectiveTest extends DirectiveTestCase
{
    private static function schemaWithArguments(string $arguments): string
    {
        return /* @lang GraphQL */ "
            type Query {
                project: Project @first
            }

            enum TimeInterval {
                HOUR @enum(value: \"hour\")
                DAY @enum(value: \"day\")
            }

            enum BuildTimeField {
                START_TIME @enum(value: \"starttime\")
                SUBMISSION_TIME @enum(value: \"submittime\")
            }

            type CountHistogramBucket {
                start: String!
                count: Int!
            }

            type Build {
                id: ID! @filterable
            }

            type Project {
                buildCountHistogram({$arguments}): [CountHistogramBucket!]! @countHistogram(relation: \"builds\")
            }
        ";
    }

    public function testAcceptsRequiredArgumentsAlongsideOtherArguments(): void
    {
        // The arguments of the buildCountHistogram fields in the real schema.
        $documentAST = $this->buildValidSchema(self::schemaWithArguments('
            filters: _ @filter(inputType: "BuildFilterInput")
            onlyParents: Boolean = true @scope
            interval: TimeInterval!
            by: BuildTimeField! = START_TIME
            timezone: String! = "UTC" @rules(apply: ["timezone"])
        '));

        self::assertSame([
            'filters' => 'ProjectBuildCountHistogramFiltersMultiFilterInput @filter(inputType: "BuildFilterInput")',
            'onlyParents' => 'Boolean = true @scope',
            'interval' => 'TimeInterval!',
            'by' => 'BuildTimeField! = START_TIME',
            'timezone' => 'String! = "UTC" @rules(apply: ["timezone"])',
        ], self::fieldArguments($documentAST, 'Project', 'buildCountHistogram'));
    }

    /**
     * @return array<string,array{string, string}>
     */
    public static function invalidArguments(): array
    {
        $interval = '`interval: TimeInterval!`';
        $by = '`by`, a non-null enum of timestamp columns';
        $timezone = '`timezone: String!`';

        return [
            'missing interval' => ['by: BuildTimeField!, timezone: String!', $interval],
            'nullable interval' => ['interval: TimeInterval, by: BuildTimeField!, timezone: String!', $interval],
            'interval of the wrong type' => ['interval: String!, by: BuildTimeField!, timezone: String!', $interval],
            'missing by' => ['interval: TimeInterval!, timezone: String!', $by],
            'nullable by' => ['interval: TimeInterval!, by: BuildTimeField, timezone: String!', $by],
            'by is not an enum' => ['interval: TimeInterval!, by: String!, timezone: String!', $by],
            'by is a list' => ['interval: TimeInterval!, by: [BuildTimeField!]!, timezone: String!', $by],
            'missing timezone' => ['interval: TimeInterval!, by: BuildTimeField!', $timezone],
            'nullable timezone' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: String', $timezone],
            'timezone of the wrong type' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: ID!', $timezone],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function testRejectsInvalidArguments(string $arguments, string $requiredArgument): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage("The @countHistogram directive on Project.buildCountHistogram requires the argument {$requiredArgument}.");

        $this->buildSchema(self::schemaWithArguments($arguments));
    }
}
