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

            scalar Time

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
            dayStart: Time! = "00:00:00" @rules(apply: ["date_format:H:i:s"])
        '));

        self::assertSame([
            'filters' => 'ProjectBuildCountHistogramFiltersMultiFilterInput @filter(inputType: "BuildFilterInput")',
            'onlyParents' => 'Boolean = true @scope',
            'interval' => 'TimeInterval!',
            'by' => 'BuildTimeField! = START_TIME',
            'timezone' => 'String! = "UTC" @rules(apply: ["timezone"])',
            'dayStart' => 'Time! = "00:00:00" @rules(apply: ["date_format:H:i:s"])',
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
        $dayStart = '`dayStart: Time!`';

        return [
            'missing interval' => ['by: BuildTimeField!, timezone: String!, dayStart: Time!', $interval],
            'nullable interval' => ['interval: TimeInterval, by: BuildTimeField!, timezone: String!, dayStart: Time!', $interval],
            'interval of the wrong type' => ['interval: String!, by: BuildTimeField!, timezone: String!, dayStart: Time!', $interval],
            'missing by' => ['interval: TimeInterval!, timezone: String!, dayStart: Time!', $by],
            'nullable by' => ['interval: TimeInterval!, by: BuildTimeField, timezone: String!, dayStart: Time!', $by],
            'by is not an enum' => ['interval: TimeInterval!, by: String!, timezone: String!, dayStart: Time!', $by],
            'by is a list' => ['interval: TimeInterval!, by: [BuildTimeField!]!, timezone: String!, dayStart: Time!', $by],
            'missing timezone' => ['interval: TimeInterval!, by: BuildTimeField!, dayStart: Time!', $timezone],
            'nullable timezone' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: String, dayStart: Time!', $timezone],
            'timezone of the wrong type' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: ID!, dayStart: Time!', $timezone],
            'missing dayStart' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: String!', $dayStart],
            'nullable dayStart' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: String!, dayStart: Time', $dayStart],
            'dayStart of the wrong type' => ['interval: TimeInterval!, by: BuildTimeField!, timezone: String!, dayStart: String!', $dayStart],
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
