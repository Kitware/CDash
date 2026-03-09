<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Models\Repository;
use Illuminate\Support\Facades\Gate;

final class CreateRepository extends AbstractMutation
{
    public ?Repository $repository = null;

    /**
     * @param array{
     *     projectId: int,
     *     url: string,
     *     username: string,
     *     password: string,
     *     branch: string,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $project = Project::find((int) $args['projectId']);

        Gate::authorize('createRepository', $project);

        $this->repository = $project?->repositories()->create([
            'url' => $args['url'],
            'username' => $args['username'],
            'password' => $args['password'],
            'branch' => $args['branch'],
        ]);

        return $this;
    }
}
