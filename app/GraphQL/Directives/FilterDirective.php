<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;

final class FilterDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
                directive @filter(
                    "Input type to filter on.  This should be of the form: <...>FilterInput"
                    inputType: String
                ) on ARGUMENT_DEFINITION
            GRAPHQL;
    }

    /**
     * @throws SyntaxError
     * @throws JsonException
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $multiFilterName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . 'MultiFilterInput';
        $argDefinition->type = Parser::namedType($multiFilterName);

        $defaultFilterType = Str::replaceEnd('Connection', '', $parentField->type->type->name->value) . 'FilterInput';
        $inputType = $this->directiveArgValue('inputType', $defaultFilterType);
        $documentAST->setTypeDefinition($this->createMultiFilterInput($multiFilterName, $inputType));
    }

    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @param Builder|EloquentBuilder<Model>|Relation<Model> $builder the builder used to resolve the field
     * @param mixed $value the client given value of the argument
     *
     * @return Builder|EloquentBuilder<Model>|Relation<Model> the modified builder
     */
    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('$value parameter must be array');
        }

        $this->applyFilters($builder, $value);

        return $builder;
    }

    protected function applyFilters(QueryBuilder|EloquentBuilder|Relation $builder, mixed $filter, string $context = 'and', ?string $table = null): void
    {
        // The query builder isn't able to provide the table being queried, so we pass it down the chain from
        // eloquent-derived builders which can.
        if ($builder instanceof EloquentBuilder || $builder instanceof Relation) {
            $table = $builder->getModel()->getTable();
        }

        if (array_key_exists('all', $filter)) {
            $builder->whereNested(
                function ($subfilterBuilder) use ($filter, $table): void {
                    foreach ($filter['all'] as $subfilter) {
                        $this->applyFilters($subfilterBuilder, $subfilter, 'and', $table);
                    }
                },
                $context
            );
        } elseif (array_key_exists('any', $filter)) {
            $builder->whereNested(
                function ($subfilterBuilder) use ($filter, $table): void {
                    foreach ($filter['any'] as $subfilter) {
                        $this->applyFilters($subfilterBuilder, $subfilter, 'or', $table);
                    }
                },
                $context,
            );
        } else {
            $operators = [
                'eq' => '=',
                'ne' => '!=',
                'gt' => '>',
                'lt' => '<',
            ];

            foreach ($operators as $operator => $sqlOperator) {
                if (array_key_exists($operator, $filter)) {
                    $key = array_key_first($filter[$operator]);
                    $value = $filter[$operator][$key];

                    if ($context === 'and') {
                        $builder->where($table . '.' . $key, $sqlOperator, $value);
                    } elseif ($context === 'or') {
                        $builder->orWhere($table . '.' . $key, $sqlOperator, $value);
                    } else {
                        throw new InvalidArgumentException('$context must be "and" or "or"');
                    }

                    break;
                }
            }

            // The "contains" filter requires special treatment
            if (array_key_exists('contains', $filter)) {
                $key = array_key_first($filter['contains']);
                $value = $filter['contains'][$key];

                $query = "POSITION(? IN {$table}.{$key}) > 0";

                if ($context === 'and') {
                    $builder->whereRaw($query, [$value]);
                } elseif ($context === 'or') {
                    $builder->orWhereRaw($query, [$value]);
                } else {
                    throw new InvalidArgumentException('$context must be "and" or "or"');
                }
            }
        }
    }

    /**
     * @throws SyntaxError
     * @throws JsonException
     */
    protected function createMultiFilterInput(string $multiFilterName, string $filterName): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
                input {$multiFilterName} {
                    "Find nodes which match at least one of the provided filters."
                    any: [{$multiFilterName}]
                    "Find nodes which match all of the provided filters."
                    all: [{$multiFilterName}]
                    "Find nodes where the provided field is equal to the provided value."
                    eq: {$filterName}
                    "Find nodes where the provided field is not equal to the provided value."
                    ne: {$filterName}
                    "Find nodes where the provided field is greater than the provided value."
                    gt: {$filterName}
                    "Find nodes where the provided field is less than the provided value."
                    lt: {$filterName}
                    "Find nodes where the provided field contains the provided value."
                    contains: {$filterName}
                }
            GRAPHQL
        );
    }
}
