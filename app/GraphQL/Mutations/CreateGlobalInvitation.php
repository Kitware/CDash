<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\GlobalRole;
use App\Exceptions\GraphQLMutationException;
use App\Mail\InvitedToCdash;
use App\Models\GlobalInvitation;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CreateGlobalInvitation extends AbstractMutation
{
    public ?GlobalInvitation $invitedUser = null;

    /**
     * @param array{
     *     email: string,
     *     role: GlobalRole,
     * } $args
     *
     * @throws GraphQLMutationException
     * @throws Exception
     */
    public function __invoke(null $_, array $args): self
    {
        // This field might not reset when testing since the same mocked request is reused.
        $this->invitedUser = null;

        Validator::make($args, [
            'email' => [
                'required',
                'email:strict',
            ],
            'role' => [
                'required',
                Rule::enum(GlobalRole::class),
            ],
        ])->validate();

        /** @var ?User $user */
        $user = auth()->user();
        if ($user === null) {
            throw new GraphQLMutationException('Attempt to invite user when not signed in.');
        }

        if ($user->cannot('createInvitation', GlobalInvitation::class)) {
            throw new GraphQLMutationException('This action is unauthorized.');
        }

        if (GlobalInvitation::where('email', $args['email'])->exists()) {
            throw new GraphQLMutationException('Duplicate invitations are not allowed.');
        }

        if (User::where('email', $args['email'])->exists()) {
            throw new GraphQLMutationException('User is already a member of this instance.');
        }

        $password = Str::password();

        $this->invitedUser = GlobalInvitation::create([
            'email' => $args['email'],
            'invited_by_id' => $user->id,
            'role' => $args['role'],  // Note: we assume that anyone who can invite users can assign them any role.
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make($password),
        ]);

        // The email gets sent to the queue, so we have no way to know immediately whether it was sent or not.
        // TODO: We should eventually track whether the email was actually sent.
        Mail::to($args['email'])->send(new InvitedToCdash($this->invitedUser, $password));

        return $this;
    }
}
