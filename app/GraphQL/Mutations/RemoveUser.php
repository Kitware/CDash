<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class RemoveUser extends AbstractMutation
{
    /**
     * @param array{
     *     userId: int,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $user = User::find((int) $args['userId']);

        if ($user === null) {
            abort(404, 'User does not exist.');
        }

        Gate::authorize('delete', $user);

        $user->delete();
    }
}
