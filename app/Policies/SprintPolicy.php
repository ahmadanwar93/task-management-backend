<?php

namespace App\Policies;

use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;

class SprintPolicy
{
    public function complete(User $user, Workspace $workspace)
    {
        return $user->isOwnerOfWorkspace($workspace);
    }
    public function index(User $user, Workspace $workspace)
    {
        return $user->canAccessWorkspace($workspace);
    }
    public function show(User $user, Workspace $workspace)
    {
        return $user->canAccessWorkspace($workspace);
    }
    public function store(User $user, Workspace $workspace)
    {
        return $user->isOwnerOfWorkspace($workspace);
    }

    public function edit(User $user, Workspace $workspace)
    {
        return $user->isOwnerOfWorkspace($workspace);
    }
}
