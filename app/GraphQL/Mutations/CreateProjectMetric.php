<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Models\ProjectMetric;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateProjectMetric extends AbstractMutation
{
    public ?ProjectMetric $projectMetric = null;

    /**
     * @param array{
     *     projectId: string,
     *     name: string,
     * } $args
     *
     * @throws ValidationException
     */
    public function __invoke(null $_, array $args): self
    {
        $project = Project::find((int) $args['projectId']);

        // Authorize before validating, so the validation errors can't reveal the metrics of a private project.
        Gate::authorize('createProjectMetric', $project);

        Validator::make($args, [
            'name' => [
                'required',
                'max:25',
                Rule::unique(ProjectMetric::class, 'name')->where('project_id', $project?->id),
            ],
        ], [
            'name.max' => 'Metric names can be at most 25 characters long.',
            'name.unique' => 'A metric with this name already exists.',
        ])->validate();

        /** @var ?int $maxPosition */
        $maxPosition = $project?->metrics()->max('position');

        $this->projectMetric = $project?->metrics()->create([
            'name' => $args['name'],
            'position' => ($maxPosition ?? 0) + 1,
        ]);

        $user = auth()->user();
        Log::info("User {$user?->id} created metric {$this->projectMetric?->id} for project {$project?->id}.");

        return $this;
    }
}
