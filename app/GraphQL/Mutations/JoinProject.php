<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Models\User;
use Exception;

final class JoinProject extends AbstractMutation
{
    /**
     * @param array{
     *     projectId: int,
     * } $args
     *
     * @throws Exception
     */
    protected function mutate(array $args): void
    {
        /** @var ?User $user */
        $user = auth()->user();
        $project = isset($args['projectId']) ? Project::find((int) $args['projectId']) : null;

        if (
            $user === null
            || $project === null
            || $user->cannot('join', $project)
        ) {
            abort(401, 'This action is unauthorized.');
        }

        $project
            ->users()
            ->attach($user->id, [
                'emailtype' => 0,
                'emailcategory' => 62,
                'emailsuccess' => false,
                'emailmissingsites' => false,
                'role' => Project::PROJECT_USER,
            ]);
    }
}
