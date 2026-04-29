<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\GlobalInvitation;
use Illuminate\Support\Facades\Gate;

final class RevokeGlobalInvitation extends AbstractMutation
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
        $invitation = GlobalInvitation::find((int) $args['invitationId']);

        if ($invitation === null) {
            throw new GraphQLMutationException('Invitation does not exist.');
        }

        Gate::authorize('revokeInvitation', $invitation);

        $invitation->delete();

        return $this;
    }
}
