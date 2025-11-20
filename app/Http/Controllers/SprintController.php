<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSprintRequest;
use App\Http\Requests\UpdateSprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    use ApiResponse;
    public function index(Request $request, string $slug)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }
        $query = $workspace->sprints();
        if ($request->has('status')) {
            $status = $request->query('status');

            if (!in_array($status, ['planned', 'active', 'completed'])) {
                return $this->errorResponse(null, 'Invalid status. Must be: planned, active, or completed', 422);
            }

            $query->where('status', $status);
        }

        $this->authorize('index', [Sprint::class, $workspace]);

        $sprints = $query
            ->withCount('tasks')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            SprintResource::collection($sprints),
            'Sprints retrieved successfully',
        );
    }

    public function show(Request $request, string $slug, string $sprintId)
    {
        // TODO: maybe remove this, and all use the index in the task controller
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $this->authorize('show', [Sprint::class, $workspace]);

        $sprint = $workspace->sprints()->find($sprintId);
        if (!$sprint) {
            return $this->errorResponse(null, 'Sprint not found', 404);
        }

        $sprint->load('tasks');

        return $this->successResponse(
            new SprintResource($sprint),
            'Sprint retrieved successfully',
        );
    }

    public function complete(Request $request, string $slug, string $sprintId)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }
        $this->authorize('complete', [Sprint::class, $workspace]);

        $sprint = $workspace->sprints()->find($sprintId);
        if (!$sprint) {
            return $this->errorResponse(null, 'Sprint not found', 404);
        }

        if (!$sprint->isActive()) {
            return $this->errorResponse(
                null,
                'Only active sprints can be completed',
                422
            );
        }

        if ($sprint->isEternal()) {
            return $this->errorResponse(
                null,
                'Eternal sprints cannot be completed',
                422
            );
        }

        $nextSprint = $sprint->complete();

        return $this->successResponse(
            [
                'completed_sprint' => new SprintResource($sprint->fresh()),
                'next_sprint' => new SprintResource($nextSprint),
            ],
            'Sprint completed successfully',
        );
    }

    public function store(StoreSprintRequest $request, string $slug)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $this->authorize('store', [Sprint::class, $workspace]);

        $sprint = $workspace->sprints()->create($request->validated());

        return $this->successResponse(
            new SprintResource($sprint),
            'Sprint created successfully',
            201
        );
    }

    public function edit(UpdateSprintRequest $request, string $slug, string $sprintId)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(null, 'Workspace not found', 404);
        }

        $this->authorize('edit', [Sprint::class, $workspace]);

        $sprint = $workspace->sprints()->find($sprintId);
        if (!$sprint) {
            return $this->errorResponse(null, 'Sprint not found', 404);
        }

        $sprint->update($request->validated());

        return $this->successResponse(
            new SprintResource($sprint),
            'Sprint updated successfully',
        );
    }
}
