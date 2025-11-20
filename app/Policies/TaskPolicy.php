<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

class TaskPolicy
{
    public function viewAny(User $user, Workspace $workspace)
    {
        return $workspace->hasMember($user);
    }

    public function view(User $user, Task $task)
    {
        return $task->workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace)
    {
        return $user->isOwnerOfWorkspace($workspace);
    }

    public function update(User $user, Task $task)
    {
        return $user->isOwnerOfWorkspace($task->workspace);
    }

    public function move(User $user, Task $task)
    {
        return ($task->assignedTo?->id == $user->id) || $user->isOwnerOfWorkspace($task->workspace);
    }

    public function delete(User $user, Task $task)
    {
        return $user->isOwnerOfWorkspace($task->workspace);
    }
}
