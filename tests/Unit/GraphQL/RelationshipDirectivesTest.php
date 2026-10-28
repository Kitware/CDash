<?php

namespace Tests\Unit\GraphQL;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\ModelDirective;
use Nuwave\Lighthouse\Schema\Directives\RenameDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Utils;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;
use Tests\Traits\DisablesSchemaCache;

/**
 * Without a resolver directive, Lighthouse reads a relationship as a model property, which lazy loads it
 * once per row.  Relation directives such as @belongsTo or @hasMany batch load the relationship instead.
 */
class RelationshipDirectivesTest extends TestCase
{
    use DisablesSchemaCache;

    public function testRelationshipFieldsHaveResolverDirectives(): void
    {
        $this->disableSchemaCache();

        $directiveLocator = app(DirectiveLocator::class);

        $fieldsMissingDirectives = [];
        foreach (app(ASTBuilder::class)->documentAST()->types as $type) {
            if (!$type instanceof ObjectTypeDefinitionNode) {
                continue;
            }

            // Resolve the model the same way Lighthouse does.
            $modelClass = Utils::namespaceClassname(
                ModelDirective::modelClass($type) ?? $type->name->value,
                config('lighthouse.namespaces.models'),
                static fn (string $classCandidate): bool => is_subclass_of($classCandidate, Model::class),
            );
            if ($modelClass === null) {
                continue;
            }

            foreach ($type->fields as $field) {
                $attribute = $field->name->value;
                foreach ($field->directives as $directive) {
                    $directiveClass = $directiveLocator->resolve($directive->name->value);
                    if ($directiveClass === RenameDirective::class) {
                        // @rename reads the attribute as a model property too, so it doesn't batch load relationships.
                        $attribute = ASTHelper::directiveArgValue($directive, 'attribute');
                        self::assertIsString($attribute);
                    } elseif (is_a($directiveClass, FieldResolver::class, true)) {
                        continue 2;
                    }
                }

                if (!method_exists($modelClass, $attribute)) {
                    continue;
                }

                // PHP method names are case-insensitive, but Eloquent reads a column like "builderrors"
                // before it falls back to a relationship like buildErrors().
                $method = new ReflectionMethod($modelClass, $attribute);
                if ($method->getName() !== $attribute) {
                    continue;
                }

                $returnType = $method->getReturnType();
                if ($returnType instanceof ReflectionNamedType && is_a($returnType->getName(), Relation::class, true)) {
                    $fieldsMissingDirectives[] = "{$type->name->value}.{$field->name->value}";
                }
            }
        }

        self::assertSame([], $fieldsMissingDirectives, 'Relationship fields must use a relation directive such as @belongsTo or @hasMany.');
    }
}
