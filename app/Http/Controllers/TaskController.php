<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ApiResponse;

    public function index(Request $request, string $slug)
    {
        $workspace = $request->user()->workspaces()->where("slug", $slug)->first();

        if (!$workspace) {
            return $this->errorResponse(null, "Workspace not found", 404);
        }

        $this->authorize('viewAny', [Task::class, $workspace]);

        $query = $workspace->tasks();

        if ($request->has('sprint_id')) {
            $query->where('sprint_id', $request->query('sprint_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->query('backlog') === 'true') {
            $query->whereNull('sprint_id');
        }

        $query->with(['assignedTo', 'createdBy', 'sprint']);



        if ($request->has('assigned_to')) {
            $assignedTo = $request->query('assigned_to');

            if ($assignedTo === 'me') {
                $query->where('assigned_to', $request->user()->id);
            } elseif ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $assignedTo);
            }
        }

        if ($request->has('created_by')) {
            $createdBy = $request->query('created_by');

            if ($createdBy === 'me') {
                $query->where('created_by', $request->user()->id);
            } else {
                $query->where('created_by', $createdBy);
            }
        }

        $tasks = $query->ordered()
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            TaskResource::collection($tasks),
            'Tasks retrieved successfully',
        );
    }

    public function store(StoreTaskRequest $request, string $slug)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $this->authorize('create', [Task::class, $workspace]);

        $validated = $request->validated();

        $validated['workspace_id'] = $workspace->id;

        $validated['created_by'] = $request->user()->id;

        if (!isset($validated['sprint_id']) && (!isset($validated['status']) || $validated['status'] !== 'backlog')) {
            // we want to allow for user to create a task with no sprint id, but has status to automatically gets assigned to the active sprint.
            // so the user does not has to manually select the sprint
            $activeSprint = $workspace->activeSprint;
            if ($activeSprint) {
                $validated['sprint_id'] = $activeSprint->id;
            }
        }

        if (!isset($validated['status'])) {
            $validated['status'] = $validated['sprint_id'] ? 'todo' : 'backlog';
        }

        if (!isset($validated['order'])) {
            $maxOrder = $workspace->tasks()
                ->where('status', $validated['status'])
                ->max('order');
            $validated['order'] = ($maxOrder ?? -1) + 1;
        }

        if (!isset($validated['assigned_to'])) {
            $validated['assigned_to'] = $request->user()->id;
        }

        $task = Task::create($validated);

        $task->load(['assignedTo', 'createdBy', 'sprint']);

        return $this->successResponse(
            new TaskResource($task),
            'Task created successfully',
            201
        );
    }

    public function show(Request $request, string $slug, int $taskId)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $task = $workspace->tasks()->find($taskId);

        if (!$task) {
            return $this->errorResponse(null, 'Task not found', 404);
        }

        $this->authorize('view', $task);

        $task->load(['assignedTo', 'createdBy', 'sprint', 'workspace']);

        return $this->successResponse(
            new TaskResource($task),
            'Task retrieved successfully',
        );
    }

    public function update(UpdateTaskRequest $request, string $slug, int $taskId)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $task = $workspace->tasks()->find($taskId);

        if (!$task) {
            return $this->errorResponse(null, 'Task not found', 404);
        }

        $this->authorize('update', $task);

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] !== $task->status) {
            if ($validated['status'] === 'done' && !$task->completed_at) {
                $validated['completed_at'] = now();
            } elseif ($validated['status'] !== 'done' && $task->completed_at) {
                $validated['completed_at'] = null;
            }
        }

        $task->update($validated);

        $task->load(['assignedTo', 'createdBy', 'sprint']);

        return $this->successResponse(
            new TaskResource($task),
            'Task updated successfully',
        );
    }

    public function destroy(Request $request, string $slug, int $taskId)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $task = $workspace->tasks()->find($taskId);

        if (!$task) {
            return $this->errorResponse(null, 'Task not found', 404);
        }

        $this->authorize('delete', $task);

        $task->delete();

        return $this->successResponse(
            null,
            'Task deleted successfully',
        );
    }

    public function move(MoveTaskRequest $request, string $slug, int $taskId)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $task = $workspace->tasks()->find($taskId);

        if (!$task) {
            return $this->errorResponse(null, 'Task not found', 404);
        }

        $this->authorize('move', $task);

        $validated = $request->validated();
        $updates = [];

        if (isset($validated['status'])) {
            $oldStatus = $task->status;
            $newStatus = $validated['status'];

            $updates['status'] = $newStatus;

            if ($newStatus === 'done' && $oldStatus !== 'done') {
                $updates['completed_at'] = now();
            } elseif ($newStatus !== 'done' && $oldStatus === 'done') {
                $updates['completed_at'] = null;
            }

            if ($oldStatus !== $newStatus && !isset($validated['order'])) {
                $maxOrder = $workspace->tasks()
                    ->where('status', $newStatus)
                    ->where('id', '!=', $task->id)
                    ->max('order');
                $updates['order'] = ($maxOrder ?? -1) + 1;
            }
        }

        if (isset($validated['order'])) {
            $newOrder = $validated['order'];
            $status = $validated['status'] ?? $task->status;

            $tasksInColumn = $workspace->tasks()
                ->where('status', $status)
                ->where('id', '!=', $task->id)
                ->ordered()
                ->get();

            $currentIndex = 0;
            foreach ($tasksInColumn as $otherTask) {
                if ($currentIndex === $newOrder) {
                    $currentIndex++;
                }

                $otherTask->update(['order' => $currentIndex]);
                $currentIndex++;
            }

            $updates['order'] = $newOrder;
        }

        $task->update($updates);

        $task->load(['assignedTo', 'createdBy', 'sprint']);

        return $this->successResponse(
            new TaskResource($task),
            'Task moved successfully',
        );
    }
}
