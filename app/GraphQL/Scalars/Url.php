<?php

declare(strict_types=1);

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Support\Facades\Validator;

final class Url extends ScalarType
{
    protected function validate(mixed $value): bool
    {
        return Validator::make([
            'value' => $value,
        ], [
            'value' => 'url',
        ])->passes();
    }

    /**
     * Serializes an internal value to include in a response.
     *
     * @throws InvariantViolation
     */
    public function serialize(mixed $value): string
    {
        if (!$this->validate($value)) {
            throw new InvariantViolation("Could not serialize {$value} as URL.");
        }

        return $this->parseValue($value);
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     *
     * @throws InvariantViolation
     */
    public function parseValue(mixed $value): string
    {
        if (!$this->validate($value)) {
            throw new InvariantViolation("Could not parse {$value} as URL.");
        }

        return (string) $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
     *
     * Should throw an exception with a client friendly message on invalid value nodes.
     *
     * @param ValueNode&Node $valueNode
     * @param array<string, mixed>|null $variables
     *
     * @throws Error
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!($valueNode instanceof StringValueNode)) {
            throw new Error("Query error: Can only parse Strings, got {$valueNode->kind}.", $valueNode);
        }

        return (string) $valueNode->value;
    }
}
