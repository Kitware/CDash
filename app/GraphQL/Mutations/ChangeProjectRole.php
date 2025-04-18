<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChangeProjectRole extends AbstractMutation
{
    public ?User $user = null;
    public ?Project $project = null;

    /**
     * @param array{
     *     userId: int,
     *     projectId: int,
     *     role: ProjectRole,
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
            'projectId' => [
                'required',
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

        $userToChange = User::find((int) $args['userId']);
        if ($userToChange === null) {
            $this->message = 'Cannot change role for user which does not exist.';
            return;
        }

        $project = Project::find((int) $args['projectId']);
        if ($project === null || $user->cannot('changeUserRole', [$project, $userToChange])) {
            $this->message = 'This action is unauthorized.';
            return;
        }

        $rowsEdited = $userToChange->projects()->updateExistingPivot($project->id, [
            'role' => match ($args['role']) {
                ProjectRole::USER => Project::PROJECT_USER,
                ProjectRole::ADMINISTRATOR => Project::PROJECT_ADMIN,
            },
        ]);

        if ($rowsEdited !== 1) {
            throw new Exception('Failed to update pivot table with new role.');
        }

        $this->user = $userToChange;
        $this->project = $project;
    }
}
