<?php

namespace App\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;

class GraphQLMutationException extends Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
