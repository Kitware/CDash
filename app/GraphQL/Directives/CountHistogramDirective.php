<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use GraphQL\Error\Error;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Directives\RelationDirectiveHelpers;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class CountHistogramDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    use RelationDirectiveHelpers;

    public const MAX_BUCKETS = 1000;

    /**
     * The arguments read by the resolver, mapped to their required types.  Null means any enum.
     */
    private const REQUIRED_ARGUMENTS = [
        'interval' => 'TimeInterval',
        'by' => null,
        'timezone' => 'String',
        'dayStart' => 'Time',
    ];

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
                """
                Count the related models in each time interval.  Only intervals containing at least one
                model are returned, ordered from oldest to newest.

                The field must declare the following non-null arguments:
                - `interval: TimeInterval!`
                - `by`: an enum whose values are timestamp columns of the related model
                - `timezone: String!`: the time zone used to determine interval boundaries
                - `dayStart: Time!`: the time of day at which intervals of a day or longer start, in that time zone
                """
                directive @countHistogram(
                    "The relationship to count."
                    relation: String!

                    "Apply scopes to the underlying query."
                    scopes: [String!]
                ) on FIELD_DEFINITION
            GRAPHQL;
    }

    /**
     * @throws DefinitionException
     */
    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $argumentTypes = [];
        foreach ($fieldDefinition->arguments as $argument) {
            $argumentTypes[$argument->name->value] = $argument->type;
        }

        foreach (self::REQUIRED_ARGUMENTS as $name => $requiredType) {
            $type = $argumentTypes[$name] ?? null;
            $typeName = $type instanceof NonNullTypeNode && $type->type instanceof NamedTypeNode ? $type->type->name->value : null;
            $isValid = $requiredType === null
                ? $typeName !== null && ($documentAST->types[$typeName] ?? null) instanceof EnumTypeDefinitionNode
                : $typeName === $requiredType;

            if (!$isValid) {
                $description = $requiredType === null ? "`{$name}`, a non-null enum of timestamp columns" : "`{$name}: {$requiredType}!`";
                throw new DefinitionException("The @{$this->name()} directive on {$parentType->name->value}.{$this->nodeName()} requires the argument {$description}.");
            }
        }
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): array {
            if (!$root instanceof Model) {
                throw new InvalidArgumentException("The @{$this->name()} directive can only be used on fields of models.");
            }

            $column = $args['by'];
            $interval = $args['interval'];
            $timezone = $args['timezone'];
            $dayStart = $args['dayStart'];
            if (
                !is_string($column) || preg_match('/^\w+$/', $column) !== 1
                || !is_string($interval) || !is_string($timezone)
                || !is_string($dayStart) || preg_match('/^\d{2}:\d{2}:\d{2}$/', $dayStart) !== 1
            ) {
                throw new InvalidArgumentException('Invalid histogram arguments.');
            }

            // Build the relationship query the way eager loading does, which is also how @count builds
            // the query for buildCount.  Unlike $root->{$this->relation()}(), the result is typed for
            // static analysis.
            $relation = $root->newQueryWithoutRelationships()->getRelation($this->relation());
            $relation->addEagerConstraints([$root]);

            $builder = $relation->getQuery();
            $this->makeBuilderDecorator($root, $args, $context, $resolveInfo)($builder);

            // Use the underlying query builder directly.  BelongsToMany::get() adds the pivot
            // columns to the select list, which is incompatible with GROUP BY.
            $query = $builder->toBase();
            $column = $query->getGrammar()->wrap($relation->getRelated()->qualifyColumn($column));

            // Timestamps are stored in UTC without a time zone.  Longer intervals are truncated on the wall
            // clock of the requested time zone after shifting by the day start, so a day spanning a daylight
            // saving time change is 23 or 25 hours long like a testing day.  Hours are truncated in the time
            // zone directly, which keeps the repeated hour at the end of daylight saving time separate.
            // Either way, each bucket start is a timestamptz, so it includes its UTC offset.
            [$bucketSql, $bindings] = $interval === 'hour'
                ? ["date_trunc(?, {$column} AT TIME ZONE 'UTC', ?)", [$interval, $timezone]]
                : ["(date_trunc(?, ({$column} AT TIME ZONE 'UTC' AT TIME ZONE ?) - ?::interval) + ?::interval) AT TIME ZONE ?", [$interval, $timezone, $dayStart, $dayStart, $timezone]];
            $buckets = $query
                ->selectRaw("{$bucketSql} AS bucket, COUNT(*) AS count", $bindings)
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->limit(self::MAX_BUCKETS + 1)
                ->get();

            if ($buckets->count() > self::MAX_BUCKETS) {
                throw new Error('More than ' . self::MAX_BUCKETS . ' intervals matched.  Narrow the filters or choose a longer interval.');
            }

            return $buckets->map(fn ($bucket): array => [
                'start' => Carbon::parse($bucket->bucket)->setTimezone($timezone),
                'count' => (int) $bucket->count,
            ])->all();
        };
    }
}
