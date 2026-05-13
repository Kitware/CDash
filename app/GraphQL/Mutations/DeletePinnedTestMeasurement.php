<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class DeletePinnedTestMeasurement extends AbstractMutation
{
    /**
     * @param array{
     *     id: int,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $measurement = PinnedTestMeasurement::find((int) $args['id']);

        Gate::authorize('deletePinnedTestMeasurement', $measurement?->project);

        $measurement?->delete();

        $user = auth()->user();
        Log::info("User {$user?->id} deleted pinned test measurement {$args['id']}.");

        return $this;
    }
}
