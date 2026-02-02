<?php

namespace App\GraphQL\Scalars;

use Carbon\Exceptions\InvalidFormatException;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Support\Carbon;

final class Time extends ScalarType
{
    public function serialize(mixed $value): string
    {
        return $value;
    }

    /**
     * @throws InvariantViolation
     */
    public function parseValue(mixed $value): string
    {
        if (!$this->validateTime($value)) {
            throw new InvariantViolation("The value $value is not a valid Time format (H:i:s).");
        }

        return $value;
    }

    /**
     * @param ValueNode&Node $valueNode
     * @param array<string, mixed>|null $variables
     *
     * @throws InvariantViolation
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!property_exists($valueNode, 'value')) {
            throw new InvariantViolation('Time must be a string.');
        }

        if (!$this->validateTime($valueNode->value)) {
            throw new InvariantViolation("The value {$valueNode->value} is not a valid Time.");
        }

        return $valueNode->value;
    }

    private function validateTime(string $time): bool
    {
        // Simple regex for HH:MM:SS* format to make sure it's actually a time, not a datetime.
        if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]/', $time) !== 1) {
            return false;
        }

        try {
            Carbon::parse($time);
            return true;
        } catch (InvalidFormatException) {
            return false;
        }
    }
}
