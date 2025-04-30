<?php

namespace App\Providers;

use App\Enums\BuildCommandType;
use App\Enums\GlobalRole;
use App\Enums\ProjectRole;
use App\Enums\TargetType;
use GraphQL\Type\Definition\PhpEnumType;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Schema\TypeRegistry;

final class GraphQLServiceProvider extends ServiceProvider
{
    public function boot(TypeRegistry $typeRegistry): void
    {
        $typeRegistry->register(new PhpEnumType(GlobalRole::class));
        $typeRegistry->register(new PhpEnumType(ProjectRole::class));
        $typeRegistry->register(new PhpEnumType(TargetType::class));
        $typeRegistry->register(new PhpEnumType(BuildCommandType::class));
    }
}
