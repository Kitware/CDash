<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class RemoveUser extends AbstractMutation
{
    /**
     * @param array{
     *     userId: int,
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        $user = User::find((int) $args['userId']);

        if ($user === null) {
            throw new GraphQLMutationException('User does not exist.');
        }

        Gate::authorize('delete', $user);

        $user->delete();

        $authUser = auth()->user();
        Log::info("User {$authUser?->id} removed user {$args['userId']}.");

        return $this;
    }
}
