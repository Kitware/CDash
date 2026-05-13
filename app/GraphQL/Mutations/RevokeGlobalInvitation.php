<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\GlobalInvitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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

        $user = auth()->user();
        Log::info("User {$user?->id} revoked global invitation for {$invitation->email}.");

        return $this;
    }
}
