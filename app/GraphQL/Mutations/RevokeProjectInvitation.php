<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\ProjectInvitation;
use Illuminate\Support\Facades\Gate;

final class RevokeProjectInvitation extends AbstractMutation
{
    /**
     * @param array{
     *     invitationId: int,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $invitation = ProjectInvitation::find((int) $args['invitationId']);

        if ($invitation === null) {
            $this->message = 'Invitation does not exist.';
            return;
        }

        Gate::authorize('revokeInvitation', $invitation->project);

        $invitation->delete();
    }
}
