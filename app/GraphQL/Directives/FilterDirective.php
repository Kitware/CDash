<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
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
use Nuwave\Lighthouse\OrderBy\OrderByDirective;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Utils;

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
     * @throws InvariantViolation
     */
    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $returnType = Str::replaceEnd('Connection', '', ASTHelper::getUnderlyingTypeName($parentField));

        $multiFilterName = ASTHelper::qualifiedArgType($argDefinition, $parentField, $parentType) . 'MultiFilterInput';
        $argDefinition->type = Parser::namedType($multiFilterName);

        $defaultFilterType = $returnType . 'FilterInput';
        $filterName = $this->directiveArgValue('inputType', $defaultFilterType);

        $subFilterableFields = $this->getSubFilterableFieldsForType($documentAST, $argDefinition, $parentType);
        foreach ($subFilterableFields as $subMultiFilterName) {
            $subTypeName = Str::before($subMultiFilterName, 'MultiFilterInput');
            $this->ensureFilterInputTypes($documentAST, $subTypeName, $argDefinition);
        }

        if (
            $subFilterableFields === []
            || (
                !($parentField->type instanceof ListTypeNode)
                && Str::doesntEndWith(ASTHelper::getUnderlyingTypeName($parentField), 'Connection')
            )
            || !isset($documentAST->types[$returnType])
            || $this->getSubFilterableFieldsForType($documentAST, $argDefinition, $documentAST->types[$returnType]) === []
        ) {
            // Don't create a relationship filter input type because this type has no relationships
            $documentAST->setTypeDefinition($this->createMultiFilterInput($multiFilterName, $filterName, null));
        } else {
            $relatedFieldRelationshipFilterName = Str::replaceEnd('Connection', '', ASTHelper::getUnderlyingTypeName($parentField)) . 'RelationshipFilterInput';
            $documentAST->setTypeDefinition($this->createMultiFilterInput($multiFilterName, $filterName, $relatedFieldRelationshipFilterName));
        }

        // We only have to create the relationship filter input type once per type, and don't create it at all
        // if there are no relationships present.
        $relationshipFilterName = $parentType->name->value . 'RelationshipFilterInput';
        if (
            !array_key_exists($relationshipFilterName, $documentAST->types)
            && $subFilterableFields !== []
        ) {
            $documentAST->setTypeDefinition(
                $this->createRelationshipFilterInput(
                    $relationshipFilterName,
                    $subFilterableFields
                )
            );
        }

        // For list/Connection fields, inject an orderBy argument covering all @filterable fields.
        $isListField = $parentField->type instanceof ListTypeNode
            || Str::endsWith(ASTHelper::getUnderlyingTypeName($parentField), 'Connection');

        if ($isListField) {
            $this->injectOrderByArgument($documentAST, $parentField, $parentType);
        }
    }

    /**
     * Inject an orderBy argument into $parentField for all @filterable fields on the return type,
     * then invoke OrderByDirective's manipulateArgDefinition so the enum types are generated.
     *
     * @throws InvariantViolation
     * @throws SyntaxError
     * @throws JsonException
     */
    private function injectOrderByArgument(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        // Avoid injecting twice if @filter is somehow applied more than once on this field
        foreach ($parentField->arguments as $existingArg) {
            if ($existingArg->name->value === 'orderBy') {
                return;
            }
        }

        $returnTypeName = Str::replaceEnd('Connection', '', ASTHelper::getUnderlyingTypeName($parentField));
        $returnType = $documentAST->types[$returnTypeName] ?? null;

        if (!$returnType instanceof ObjectTypeDefinitionNode && !$returnType instanceof InterfaceTypeDefinitionNode) {
            return;
        }

        // Collect filterable fields: GraphQL field name -> DB column name
        /** @var array<string, string> $filterableFields fieldName => dbColumn */
        $filterableFields = [];
        foreach ($returnType->fields as $field) {
            $isFilterable = false;
            foreach ($field->directives as $directive) {
                if ($directive->name->value === 'filterable') {
                    $isFilterable = true;
                    break;
                }
            }

            if (!$isFilterable) {
                continue;
            }

            $graphqlFieldName = $field->name->value;

            // Use @rename attribute value as the DB column name, otherwise use the field name
            $dbColumn = $graphqlFieldName;
            foreach ($field->directives as $directive) {
                if ($directive->name->value === 'rename') {
                    $renameValue = $directive->arguments[0]->value;
                    if (property_exists($renameValue, 'value')) {
                        $dbColumn = (string) $renameValue->value;
                    }
                    break;
                }
            }

            $filterableFields[$graphqlFieldName] = $dbColumn;
        }

        if ($filterableFields === []) {
            return;
        }

        // Build a custom columns enum using GraphQL field names as user-visible enum values,
        // with @enum(value: "dbColumn") to map each to the actual database column.
        $placeholderArg = Parser::inputValueDefinition('orderBy: _');
        $enumName = ASTHelper::qualifiedArgType(
            $placeholderArg,
            $parentField,
            $parentType,
        ) . 'OrderByColumn';

        $enumValues = [];
        foreach ($filterableFields as $fieldName => $dbColumn) {
            $enumValueName = Utils::toEnumValueName($fieldName);
            $enumValues[] = "{$enumValueName} @enum(value: \"{$dbColumn}\")";
        }
        $enumValuesString = implode("\n    ", $enumValues);

        $documentAST->setTypeDefinition(Parser::enumTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
                "Allowed columns for orderBy on this field."
                enum {$enumName} {
                    {$enumValuesString}
                }
            GRAPHQL));

        // Default to ascending order by id if id is a filterable field
        $idEnumName = isset($filterableFields['id']) ? Utils::toEnumValueName('id') : null;
        $defaultValue = $idEnumName !== null ? " = [{column: {$idEnumName}, order: ASC}]" : '';

        $orderByArg = Parser::inputValueDefinition(/* @lang GraphQL */ <<<GRAPHQL
                "Sort the results by one or more filterable fields."
                orderBy: _{$defaultValue} @orderBy(columnsEnum: "{$enumName}")
            GRAPHQL);

        $parentField->arguments[] = $orderByArg;

        // Manually invoke OrderByDirective's manipulateArgDefinition so it generates the enum types.
        // hydrate() expects the DirectiveNode itself (the @orderBy node on the arg) plus the node it is attached to.
        $orderByDirectiveNode = null;
        foreach ($orderByArg->directives as $directive) {
            if ($directive->name->value === 'orderBy') {
                $orderByDirectiveNode = $directive;
                break;
            }
        }

        if ($orderByDirectiveNode !== null) {
            /** @var OrderByDirective $orderByDirective */
            $orderByDirective = app(OrderByDirective::class);
            $orderByDirective->hydrate($orderByDirectiveNode, $orderByArg);
            $orderByDirective->manipulateArgDefinition($documentAST, $orderByArg, $parentField, $parentType);
        }
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

        if ($builder instanceof QueryBuilder) {
            throw new InvalidArgumentException('Query builder is not allowed.');
        }

        $this->applyFilters($builder, $value);

        return $builder;
    }

    protected function applyFilters(EloquentBuilder|Relation $builder, mixed $filter, string $context = 'and'): void
    {
        $table = $builder->getModel()->getTable();

        if (array_key_exists('all', $filter)) {
            $builder->where(
                function ($subfilterBuilder) use ($filter): void {
                    foreach ($filter['all'] as $subfilter) {
                        $this->applyFilters($subfilterBuilder, $subfilter, 'and');
                    }
                },
                boolean: $context,
            );
        } elseif (array_key_exists('any', $filter)) {
            $builder->where(
                function ($subfilterBuilder) use ($filter): void {
                    foreach ($filter['any'] as $subfilter) {
                        $this->applyFilters($subfilterBuilder, $subfilter, 'or');
                    }
                },
                boolean: $context,
            );
        } elseif (array_key_exists('has', $filter)) {
            $relationshipName = array_key_first($filter['has']);
            $relationshipFilters = $filter['has'][$relationshipName];
            if ($context === 'and') {
                $builder->whereHas($relationshipName, function ($subfilterBuilder) use ($relationshipFilters): void {
                    $this->applyFilters($subfilterBuilder, $relationshipFilters);
                });
            } else {
                $builder->orWhereHas($relationshipName, function ($subfilterBuilder) use ($relationshipFilters): void {
                    $this->applyFilters($subfilterBuilder, $relationshipFilters);
                });
            }
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
    protected function createMultiFilterInput(string $multiFilterName, string $filterName, ?string $relationshipFilterName): InputObjectTypeDefinitionNode
    {
        if ($relationshipFilterName !== null) {
            $hasFilter = '"Find nodes which have one or more related notes which match the provided filter."' . PHP_EOL;
            $hasFilter .= 'has: ' . $relationshipFilterName . '@rules(apply: ["prohibits:any,all,eq,ne,gt,lt,contains"])';
        } else {
            $hasFilter = '';
        }

        return Parser::inputObjectTypeDefinition(/* @lang GraphQL */ <<<GRAPHQL
                input {$multiFilterName} {
                    "Find nodes which match at least one of the provided filters."
                    any: [{$multiFilterName}] @rules(apply: ["prohibits:all,has,eq,ne,gt,lt,contains"])
                    "Find nodes which match all of the provided filters."
                    all: [{$multiFilterName}] @rules(apply: ["prohibits:any,has,eq,ne,gt,lt,contains"])
                    {$hasFilter}
                    "Find nodes where the provided field is equal to the provided value."
                    eq: {$filterName} @rules(apply: ["prohibits:any,all,has,ne,gt,lt,contains"])
                    "Find nodes where the provided field is not equal to the provided value."
                    ne: {$filterName} @rules(apply: ["prohibits:any,all,has,eq,gt,lt,contains"])
                    "Find nodes where the provided field is greater than the provided value."
                    gt: {$filterName} @rules(apply: ["prohibits:any,all,has,eq,ne,lt,contains"])
                    "Find nodes where the provided field is less than the provided value."
                    lt: {$filterName} @rules(apply: ["prohibits:any,all,has,eq,ne,gt,contains"])
                    "Find nodes where the provided field contains the provided value."
                    contains: {$filterName} @rules(apply: ["prohibits:any,all,has,eq,ne,gt,lt"])
                }
            GRAPHQL
        );
    }

    /**
     * @param array<string,string> $subFilterableFields
     *
     * @throws JsonException
     * @throws SyntaxError
     */
    protected function createRelationshipFilterInput(string $relationshipFilterName, array $subFilterableFields): InputObjectTypeDefinitionNode
    {
        $typeDefinition = "input {$relationshipFilterName} {" . PHP_EOL;
        foreach ($subFilterableFields as $fieldName => $fieldType) {
            $typeDefinition .= "{$fieldName}: {$fieldType}" . PHP_EOL;
        }
        $typeDefinition .= '}' . PHP_EOL;
        return Parser::inputObjectTypeDefinition($typeDefinition);
    }

    /**
     * Find a list of filterable relationships by finding those which return a list and have a @filter directive.
     *
     * @return array<string,string> A mapping of field names to their respective ...MultiFilterInput types
     */
    private function getSubFilterableFieldsForType(DocumentAST $documentAST, InputValueDefinitionNode $argDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $type): array
    {
        $subFilterableFieldNames = [];
        foreach ($type->fields as $field) {
            // Check for hasMany/Connection style relationships with @filter argument
            $hasFilterArg = false;
            foreach ($field->arguments as $argument) {
                if (ASTHelper::hasDirective($argument, 'filter')) {
                    $hasFilterArg = true;
                    break;
                }
            }

            if (
                $hasFilterArg
                && Str::endsWith(ASTHelper::getUnderlyingTypeName($field), 'Connection')
            ) {
                $subFilterableFieldNames[(string) $field->name->value] = ASTHelper::qualifiedArgType($argDefinition, $field, $type) . 'MultiFilterInput';
                continue;
            }

            // Check for single-record relationships
            if (
                ASTHelper::hasDirective($field, 'belongsTo')
                || ASTHelper::hasDirective($field, 'hasOne')
                || ASTHelper::hasDirective($field, 'hasOneThrough')
                || ASTHelper::hasDirective($field, 'morphOne')
                || ASTHelper::hasDirective($field, 'morphTo')
            ) {
                $typeName = ASTHelper::getUnderlyingTypeName($field);
                if ($this->isTypeFilterable($documentAST, $typeName)) {
                    $subFilterableFieldNames[(string) $field->name->value] = $typeName . 'MultiFilterInput';
                }
            }
        }
        return $subFilterableFieldNames;
    }

    private function ensureFilterInputTypes(DocumentAST &$documentAST, string $typeName, InputValueDefinitionNode $argDefinition): void
    {
        $multiFilterName = $typeName . 'MultiFilterInput';
        if (array_key_exists($multiFilterName, $documentAST->types)) {
            return;
        }

        $type = $documentAST->types[$typeName] ?? null;
        if (!$type || (!$type instanceof ObjectTypeDefinitionNode && !$type instanceof InterfaceTypeDefinitionNode)) {
            return;
        }

        // Avoid infinite recursion by setting a placeholder
        $documentAST->types[$multiFilterName] = null;

        $subFilterableFields = $this->getSubFilterableFieldsForType($documentAST, $argDefinition, $type);

        $relationshipFilterName = null;
        if ($subFilterableFields !== []) {
            $relationshipFilterName = $typeName . 'RelationshipFilterInput';
            if (!array_key_exists($relationshipFilterName, $documentAST->types)) {
                // Ensure related types exist
                foreach ($subFilterableFields as $subMultiFilterName) {
                    $subTypeName = Str::before($subMultiFilterName, 'MultiFilterInput');
                    $this->ensureFilterInputTypes($documentAST, $subTypeName, $argDefinition);
                }

                $documentAST->setTypeDefinition($this->createRelationshipFilterInput($relationshipFilterName, $subFilterableFields));
            }
        }

        $filterName = $typeName . 'FilterInput';
        $documentAST->setTypeDefinition($this->createMultiFilterInput($multiFilterName, $filterName, $relationshipFilterName));
    }

    private function isTypeFilterable(DocumentAST $documentAST, string $typeName): bool
    {
        if (!isset($documentAST->types[$typeName])) {
            return false;
        }

        $type = $documentAST->types[$typeName];
        if (!$type instanceof ObjectTypeDefinitionNode && !$type instanceof InterfaceTypeDefinitionNode) {
            return false;
        }

        foreach ($type->fields as $field) {
            if (ASTHelper::hasDirective($field, 'filterable')) {
                return true;
            }
            // Also check for arguments with @filter
            foreach ($field->arguments as $argument) {
                if (ASTHelper::hasDirective($argument, 'filter')) {
                    return true;
                }
            }
        }

        return false;
    }
}
