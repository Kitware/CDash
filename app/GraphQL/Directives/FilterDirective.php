<?php declare(strict_types=1);

namespace App\GraphQL\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;

final class FilterDirective extends BaseDirective implements ArgBuilderDirective, ArgManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
                directive @filter(
                    "Input type to filter on.  This should be of the form: <...>FilterInput"
                    inputType: String!
                ) on ARGUMENT_DEFINITION
            GRAPHQL;
    }

    /**
     * @throws \GraphQL\Error\SyntaxError
     * @throws \JsonException
     */
    public function manipulateArgDefinition(
        DocumentAST                                          &$documentAST,
        InputValueDefinitionNode                             &$argDefinition,
        FieldDefinitionNode                                  &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $multiFilterName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . 'MultiFilterInput';
        $argDefinition->type = Parser::namedType($multiFilterName);

        $documentAST->setTypeDefinition($this->createMultiFilterInput($multiFilterName, $this->directiveArgValue('inputType')));
    }

    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $builder  the builder used to resolve the field
     * @param  mixed  $value  the client given value of the argument
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model> the modified builder
     */
    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('$value parameter must be array');
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
                        throw new \InvalidArgumentException('$context must be "and" or "or"');
                    }

                    break;
                }
            }
        }
    }

    /**
     * @throws \GraphQL\Error\SyntaxError
     * @throws \JsonException
     */
    protected function createMultiFilterInput(string $multiFilterName, string $filterName): InputObjectTypeDefinitionNode
    {
        return Parser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
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
                }
            GRAPHQL
        );
    }
}
