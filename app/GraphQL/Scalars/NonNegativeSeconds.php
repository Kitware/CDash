<?php

declare(strict_types=1);

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Type\Definition\ScalarType;

final class NonNegativeSeconds extends ScalarType
{
    protected function validate(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }

    /**
     * Serializes an internal value to include in a response.
     *
     * @throws InvariantViolation
     */
    public function serialize(mixed $value): float
    {
        if (!$this->validate($value)) {
            throw new InvariantViolation("Could not serialize '{$value}' as non-negative seconds.");
        }

        return $this->parseValue($value);
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     *
     * @throws InvariantViolation
     */
    public function parseValue(mixed $value): float
    {
        if (!$this->validate($value)) {
            throw new InvariantViolation("Could not parse '{$value}' as non-negative seconds.");
        }

        return (float) $value;
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
    public function parseLiteral(Node $valueNode, ?array $variables = null): float
    {
        if (!$valueNode instanceof FloatValueNode && !$valueNode instanceof IntValueNode) {
            throw new Error("Query error: Can only parse Floats or Integers, got {$valueNode->kind}.", $valueNode);
        }

        if (!$this->validate($valueNode->value)) {
            throw new Error("Could not parse '{$valueNode->value}' as non-negative seconds.", $valueNode);
        }

        return (float) $valueNode->value;
    }
}
