<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\GlobalRole;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChangeGlobalRole extends AbstractMutation
{
    public ?User $user = null;

    /**
     * @param array{
     *     userId: int,
     *     role: GlobalRole,
     * } $args
     *
     * @throws ValidationException
     */
    protected function mutate(array $args): void
    {
        Validator::make($args, [
            'userId' => [
                'required',
            ],
            'role' => [
                'required',
                Rule::enum(GlobalRole::class),
            ],
        ])->validate();

        /** @var ?User $user */
        $user = auth()->user();
        if ($user === null) {
            // This should never happen, but we handle the case anyway to make PHPStan happy.
            throw new Exception('Attempt to invite user when not signed in.');
        }

        $userToChange = User::find((int) $args['userId']);
        if ($userToChange === null) {
            $this->message = 'Cannot change role for user which does not exist.';
            return;
        }

        if ($user->cannot('changeRole', $userToChange)) {
            $this->message = 'Insufficient permissions.';
            return;
        }

        $userToChange->admin = $args['role'] === GlobalRole::ADMINISTRATOR;
        $userToChange->save();

        $this->user = $userToChange;
    }
}
