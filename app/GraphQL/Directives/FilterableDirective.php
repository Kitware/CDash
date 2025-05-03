<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use JsonException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

final class FilterableDirective extends BaseDirective implements FieldManipulator
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
                directive @filterable on FIELD_DEFINITION
            GRAPHQL;
    }

    /**
     * @throws JsonException
     * @throws SyntaxError
     */
    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $typeName = $parentType->name->value . 'FilterInput';

        // We only have to do this once per type, even though this is called once per filterable directive
        if (!array_key_exists($typeName, $documentAST->types)) {
            $inputTypeString = "input {$typeName} {" . PHP_EOL;

            $allFieldNames = [];
            foreach ($parentType->fields as $field) {
                $allFieldNames[] = $field->name->value;
            }

            $filterableFields = [];
            foreach ($parentType->fields as $field) {
                foreach ($field->directives as $directive) {
                    if ($directive->name->value === 'filterable') {
                        $filterableFields[] = $field;
                        break;
                    }
                }
            }

            foreach ($filterableFields as $field) {
                $name = $field->name->value;
                $type = $field->type->name->value ?? $field->type->type->name->value;
                $description = $field->description?->value;

                // Only allow one field at a time.
                $fieldsToExclude = array_filter($allFieldNames, fn ($value): bool => $value !== $name);
                $validationDirective = '@rules(apply: ["prohibits:' . implode(',', $fieldsToExclude) . '"])';

                // The @rename directive is commonly used, so we handle it explicitly.  In the future, we may
                // want a more generalized approach which applies any directives which are also valid input directives.
                $renameDirective = '';
                foreach ($field->directives as $directive) {
                    if ($directive->name->value === 'rename') {
                        $renameDirective = '@rename(attribute: "' . $directive->arguments[0]->value->value . '")';
                        break;
                    }
                }

                if ($description !== null) {
                    $inputTypeString .= '"""' . PHP_EOL . $description . PHP_EOL . '"""' . PHP_EOL;
                }
                $inputTypeString .= "{$name}: $type $validationDirective $renameDirective" . PHP_EOL;
            }

            $inputTypeString .= '}';

            $documentAST->setTypeDefinition(Parser::inputObjectTypeDefinition($inputTypeString));
        }
    }
}
