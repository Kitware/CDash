<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Exceptions\GraphQLMutationException;
use App\Mail\InvitedToProject;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class InviteToProject extends AbstractMutation
{
    public ?ProjectInvitation $invitedUser = null;

    /**
     * @param array{
     *     email: string,
     *     projectId: int,
     *     role: ProjectRole,
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
            'projectId' => [
                'required', // We defer to a later authorization check to prevent leaking project names here.
            ],
            'role' => [
                'required',
                Rule::enum(ProjectRole::class),
            ],
        ])->validate();

        /** @var ?User $user */
        $user = auth()->user();
        if ($user === null) {
            // This should never happen, but we handle the case anyway to make PHPStan happy.
            throw new Exception('Attempt to invite user when not signed in.');
        }

        $project = isset($args['projectId']) ? Project::find((int) $args['projectId']) : null;
        if ($project === null || $user->cannot('inviteUser', $project)) {
            throw new GraphQLMutationException('This action is unauthorized.');
        }

        if (ProjectInvitation::where(['email' => $args['email'], 'project_id' => $args['projectId']])->exists()) {
            throw new GraphQLMutationException('Duplicate invitations are not allowed.');
        }

        if ($project->users()->where('email', $args['email'])->exists()) {
            throw new GraphQLMutationException('User is already a member of this project.');
        }

        $this->invitedUser = ProjectInvitation::create([
            'email' => $args['email'],
            'invited_by_id' => $user->id,
            'project_id' => $args['projectId'],
            'role' => $args['role'],  // Note: we assume that anyone who can invite users can assign them any role.
            'invitation_timestamp' => Carbon::now(),
        ]);

        // The email gets sent to the queue, so we have no way to know immediately whether it was sent or not.
        // TODO: We should eventually track whether the email was actually sent.
        Mail::to($args['email'])->send(new InvitedToProject($this->invitedUser));

        return $this;
    }
}
