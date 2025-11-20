<?php

namespace App\Policies;

use App\Models\User;
use App\Models\workspace;
use Illuminate\Auth\Access\Response;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, workspace $workspace): bool
    {
        return $user->canAccessWorkspace($workspace);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, workspace $workspace): bool
    {
        return $user->isOwnerOfWorkspace($workspace);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, workspace $workspace): bool
    {
        return $user->isOwnerOfWorkspace($workspace);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, workspace $workspace): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, workspace $workspace): bool
    {
        return false;
    }
}
