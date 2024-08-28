<?php

namespace Tests\Traits;

use App\Models\Project;
use Illuminate\Support\Str;

trait CreatesProjects
{
    public function makePublicProject(?string $name = null): Project
    {
        return Project::create([
            'name' => $name ?? 'PublicProject_' . Str::uuid()->toString(),
            'public' => Project::ACCESS_PUBLIC,
        ]);
    }

    public function makeProtectedProject(?string $name = null): Project
    {
        return Project::create([
            'name' => $name ?? 'ProtectedProject_' . Str::uuid()->toString(),
            'public' => Project::ACCESS_PROTECTED,
        ]);
    }

    public function makePrivateProject(?string $name = null): Project
    {
        return Project::create([
            'name' => $name ?? 'PrivateProject_' . Str::uuid()->toString(),
            'public' => Project::ACCESS_PRIVATE,
        ]);
    }
}
