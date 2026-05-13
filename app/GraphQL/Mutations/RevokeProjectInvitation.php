<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\ProjectInvitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class RevokeProjectInvitation extends AbstractMutation
{
    /**
     * @param array{
     *     invitationId: int,
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        $invitation = ProjectInvitation::find((int) $args['invitationId']);

        if ($invitation === null) {
            throw new GraphQLMutationException('Invitation does not exist.');
        }

        Gate::authorize('revokeInvitation', $invitation->project);

        $invitation->delete();

        $user = auth()->user();
        Log::info("User {$user?->id} revoked invitation for user {$invitation->email} to project {$invitation->project_id}.");

        return $this;
    }
}
