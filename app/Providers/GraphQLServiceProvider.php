<?php

namespace App\Providers;

use App\Enums\BuildMeasurementType;
use App\Enums\ProjectRole;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\PhpEnumType;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * @throws InvariantViolation
     */
    public function boot(TypeRegistry $typeRegistry): void
    {
        $typeRegistry->register(new PhpEnumType(BuildMeasurementType::class));
        $typeRegistry->register(new PhpEnumType(ProjectRole::class));
    }
}
