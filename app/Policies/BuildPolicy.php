<?php

namespace App\Policies;

use App\Models\Build;
use App\Models\User;
use BadMethodCallException;
use Illuminate\Support\Facades\Gate;

class BuildPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        throw new BadMethodCallException('Policy method viewAny not implemented for Build');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Build $build): bool
    {
        return Gate::check('view', $build->project);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        throw new BadMethodCallException('Policy method create not implemented for Build');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Build $build): bool
    {
        throw new BadMethodCallException('Policy method update not implemented for Build');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Build $build): bool
    {
        throw new BadMethodCallException('Policy method delete not implemented for Build');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Build $build): bool
    {
        throw new BadMethodCallException('Policy method restore not implemented for Build');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Build $build): bool
    {
        throw new BadMethodCallException('Policy method forceDelete not implemented for Build');
    }
}
