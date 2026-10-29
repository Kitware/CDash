<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\ProjectMetric;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateProjectMetric extends AbstractMutation
{
    public ?ProjectMetric $projectMetric = null;

    /**
     * @param array{
     *     id: string,
     *     name: string,
     * } $args
     *
     * @throws ValidationException
     */
    public function __invoke(null $_, array $args): self
    {
        $metric = ProjectMetric::find((int) $args['id']);

        // Authorize before validating, so the validation errors can't reveal the metrics of a private project.
        Gate::authorize('updateProjectMetric', $metric?->project);

        Validator::make($args, [
            'name' => [
                'required',
                'max:25',
                Rule::unique(ProjectMetric::class, 'name')
                    ->where('project_id', $metric?->project_id)
                    ->ignore($metric?->id),
            ],
        ], [
            'name.max' => 'Metric names can be at most 25 characters long.',
            'name.unique' => 'A metric with this name already exists.',
        ])->validate();

        $metric?->update([
            'name' => $args['name'],
        ]);

        $this->projectMetric = $metric;

        $user = auth()->user();
        Log::info("User {$user?->id} renamed metric {$metric?->id}.");

        return $this;
    }
}
