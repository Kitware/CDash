<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class RemoveProjectUser extends AbstractMutation
{
    /**
     * @param array{
     *     userId: int,
     *     projectId: int,
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        /** @var ?User $user */
        $user = auth()->user();
        $project = isset($args['projectId']) ? Project::find((int) $args['projectId']) : null;
        $userToRemove = isset($args['userId']) ? User::find((int) $args['userId']) : null;

        if (
            $user === null
            || $project === null
            || $userToRemove === null
            || (
                $userToRemove->id === $user->id
                && $user->cannot('leave', $project)
            )
            || (
                $userToRemove->id !== $user->id
                && $user->cannot('removeUser', $project)
            )
            || !$project->users()->where('id', $userToRemove->id)->exists()
        ) {
            throw new GraphQLMutationException('This action is unauthorized.');
        }

        $project->users()->detach($userToRemove->id);

        Log::info("User {$user->id} removed user {$userToRemove->id} from project {$project->id}.");

        return $this;
    }
}
