<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\UserInvitation;
use Illuminate\Support\Facades\Gate;

final class RevokeInvitation extends AbstractMutation
{
    /**
     * @param array{
     *     invitationId: int,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $invitation = UserInvitation::find((int) $args['invitationId']);

        if ($invitation === null) {
            $this->message = 'Invitation does not exist.';
            return;
        }

        Gate::authorize('revokeInvitations', $invitation->project);

        $invitation->delete();
    }
}
