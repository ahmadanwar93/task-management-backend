<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    use ApiResponse;

    /**
     * Create a new workspace
     */
    public function store(StoreWorkspaceRequest $request)
    {
        $validated = $request->validated();

        try {
            $workspace = DB::transaction(function () use ($validated, $request) {
                $workspace = Workspace::create([
                    'name' => $validated['name'],
                    'slug' => Workspace::generateUniqueSlug(),
                    'owner_id' => $request->user()->id,
                    'sprint_enabled' => $validated['sprint_enabled'],
                    'sprint_duration' => $validated['sprint_duration'] ?? null,
                ]);

                $workspace->addMember($request->user(), 'owner');

                return $workspace;
            });

            return $this->successResponse(
                new WorkspaceResource($workspace),
                'Workspace created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                null,
                'Failed to create workspace',
                500
            );
        }
    }

    public function index(Request $request)
    {
        $workspaces = $request->user()->workspaces()
            ->withCount('users')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            WorkspaceResource::collection($workspaces),
            'Workspaces retrieved successfully',
            200
        );
    }

    public function show(Request $request, string $slug)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->with(['users', 'owner'])
            ->first();

        if (!$workspace) {
            return $this->errorResponse(
                null,
                'Workspace not found',
                404
            );
        }

        $this->authorize('view', $workspace);

        return $this->successResponse(
            new WorkspaceResource($workspace),
            'Workspace retrieved successfully'
        );
    }

    public function update(UpdateWorkspaceRequest $request, string $slug)
    {
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->with(['users', 'owner'])
            ->first();

        if (!$workspace) {
            return $this->errorResponse(
                null,
                'Workspace not found',
                404
            );
        }

        $this->authorize('update', $workspace);
        $validated = $request->validated();

        if (array_key_exists('sprint_enabled', $validated) && !$validated['sprint_enabled']) {
            $validated['sprint_duration'] = null;
        }

        $workspace->update($validated);

        return $this->successResponse(
            new WorkspaceResource($workspace->load(['users', 'owner'])),
            'Workspace updated successfully'
        );
    }

    public function destroy(Request $request, string $slug)
    {
        $workspace = $request->user()
            ->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(
                null,
                'Workspace not found',
                404
            );
        }

        $this->authorize('delete', $workspace);

        $workspace->delete();

        return $this->successResponse(
            null,
            'Workspace deleted successfully'
        );
    }

    public function inviteMember(InviteMemberRequest $request, string $slug)
    {
        $workspace = $request->user()
            ->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(
                null,
                'Workspace not found',
                404
            );
        }

        // only owner can invite member
        $this->authorize('update', $workspace);
        $validated = $request->validated();
        $userToInvite = User::where('email', $validated['email'])->first();

        if ($workspace->hasMember($userToInvite)) {
            return $this->errorResponse(
                null,
                'This user is already a member of this workspace',
                422
            );
        }

        if ($userToInvite->id === $request->user()->id) {
            // a user cannot invite itself into the workspace
            return $this->errorResponse(
                null,
                'This user is already a member of this workspace',
                422
            );
        }

        $workspace->addMember($userToInvite, 'guest');

        return $this->successResponse(null, 'Member invited successfully', 201);
    }

    public function removeMember(Request $request, string $slug, int $userId)
    {
        $workspace = $request->user()
            ->workspaces()
            ->where('slug', $slug)
            ->first();

        if (!$workspace) {
            return $this->errorResponse(
                null,
                'Workspace not found',
                404
            );
        }

        // only owner can delete member from the workspace
        $this->authorize('delete', $workspace);

        $userToRemove = User::find($userId);

        if (!$userToRemove) {
            return $this->errorResponse(
                null,
                'User not found',
                404
            );
        }

        if (!$workspace->hasMember($userToRemove)) {
            return $this->errorResponse(
                null,
                'User is not a member of this workspace.',
                422
            );
        }

        if ($workspace->owner_id === $userId) {
            return $this->errorResponse(
                null,
                'Cannot remove the workspace owner',
                422
            );
        }

        $workspace->removeMember($userToRemove);

        return $this->successResponse(
            null,
            'Member removed successfully'
        );
    }
}
