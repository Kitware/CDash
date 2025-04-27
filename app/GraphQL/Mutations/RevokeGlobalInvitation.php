<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\GlobalInvitation;
use Illuminate\Support\Facades\Gate;

final class RevokeGlobalInvitation extends AbstractMutation
{
    /**
     * @param array{
     *     invitationId: int,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $invitation = GlobalInvitation::find((int) $args['invitationId']);

        if ($invitation === null) {
            abort(400, 'Invitation does not exist.');
        }

        Gate::authorize('revokeInvitation', $invitation);

        $invitation->delete();
    }
}
