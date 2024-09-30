<?php

namespace App\Providers;

use App\Enums\BuildMeasurementType;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use GraphQL\Type\Definition\PhpEnumType;

final class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * @throws \GraphQL\Error\InvariantViolation
     */
    public function boot(TypeRegistry $typeRegistry): void
    {
        $typeRegistry->register(new PhpEnumType(BuildMeasurementType::class));
    }
}
