<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\ProjectMetric;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class DeleteProjectMetric extends AbstractMutation
{
    /**
     * @param array{
     *     id: string,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $metric = ProjectMetric::find((int) $args['id']);

        Gate::authorize('deleteProjectMetric', $metric?->project);

        // The metric's values are deleted along with it.
        $metric?->delete();

        $user = auth()->user();
        Log::info("User {$user?->id} deleted metric {$args['id']}.");

        return $this;
    }
}
