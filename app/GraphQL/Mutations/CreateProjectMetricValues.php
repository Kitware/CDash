<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Models\ProjectMetric;
use App\Models\ProjectMetricValue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateProjectMetricValues extends AbstractMutation
{
    /** @var ?Collection<int, ProjectMetricValue> */
    public ?Collection $projectMetricValues = null;

    /**
     * @param array{
     *     projectId: string,
     *     values: array<array{
     *         name: string,
     *         value: string,
     *     }>,
     * } $args
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(null $_, array $args): self
    {
        /** @var ?User $user */
        $user = auth()->user();
        $project = Project::find((int) $args['projectId']);

        // Authorize before validating, so the validation errors can't reveal the metrics of a private project.
        if ($user === null || $project === null || $user->cannot('createProjectMetricValues', $project)) {
            throw new AuthorizationException();
        }

        Validator::make($args, [
            'values' => [
                'required',
            ],
            'values.*.name' => [
                'required',
                'distinct',
                Rule::exists(ProjectMetric::class, 'name')->where('project_id', $project->id),
            ],
            'values.*.value' => [
                'required',
                'max:25',
            ],
        ], [
            'values.required' => 'At least one value must be provided.',
            'values.*.name.distinct' => 'Only one value can be submitted for each metric.',
            'values.*.name.exists' => 'This project does not have a metric named ":input".',
            'values.*.value.max' => 'Values can be at most 25 characters long.',
        ])->validate();

        $metricIds = $project->metrics()->pluck('id', 'name');

        // Every value in a submission gets the same time.  The column only stores milliseconds.
        $createdAt = Carbon::now()->startOfMillisecond();

        $this->projectMetricValues = DB::transaction(fn (): Collection => collect($args['values'])->map(
            fn (array $value): ProjectMetricValue => ProjectMetricValue::create([
                'metric_id' => $metricIds[$value['name']],
                'user_id' => $user->id,
                'value' => $value['value'],
                'created_at' => $createdAt,
            ])
        ));

        $count = count($args['values']);
        Log::info("User {$user->id} submitted {$count} metric values for project {$project->id}.");

        return $this;
    }
}
