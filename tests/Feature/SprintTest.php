<?php

use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;

function authenticatedUser(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;
    return [$user, $token];
}

test('authenticated user can list sprints for workspace', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $workspace->addMember($user, 'owner');

    Sprint::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Sprints retrieved successfully',
        ])
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'start_date',
                    'end_date',
                    'is_eternal',
                    'days_remaining',
                    'days_elapsed',
                    'duration',
                    'tasks_count',
                    'created_at',
                    'updated_at',
                ]
            ]
        ]);
});

test('can filter sprints by active status', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    Sprint::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);
    Sprint::factory()->count(2)->create(['workspace_id' => $workspace->id, 'status' => 'completed']);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints?status=active", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.status'))->toBe('active');
});

test('can filter sprints by completed status', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    Sprint::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);
    Sprint::factory()->count(2)->create(['workspace_id' => $workspace->id, 'status' => 'completed']);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints?status=completed", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $sprint) {
        expect($sprint['status'])->toBe('completed');
    }
});

test('invalid status filter returns validation error', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints?status=invalid", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid status. Must be: planned, active, or completed',
        ]);
});

test('non-member cannot list sprints', function () {
    [$user, $token] = authenticatedUser();
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    // User is NOT a member

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);
});

test('unauthenticated user cannot list sprints', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints");

    $response->assertUnauthorized();
});

// ============================================================================
// SHOW TESTS
// ============================================================================

test('authenticated user can get sprint details', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Sprint 1',
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Sprint retrieved successfully',
            'data' => [
                'id' => $sprint->id,
                'name' => 'Sprint 1',
            ]
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'status',
                'tasks', // Should include tasks array
            ]
        ]);
});

test('sprint show includes tasks array', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk();

    expect($response->json('data'))->toHaveKey('tasks');
    expect($response->json('data.tasks'))->toBeArray();
});

test('cannot access sprint from different workspace', function () {
    [$user, $token] = authenticatedUser();
    $workspace1 = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace2 = Workspace::factory()->create();
    $workspace1->addMember($user, 'owner');

    $sprint = Sprint::factory()->create(['workspace_id' => $workspace2->id]);

    $response = $this->getJson("/api/workspaces/{$workspace1->slug}/sprints/{$sprint->id}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Sprint not found',
        ]);
});

test('returns 404 for non-existent sprint', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/sprints/99999", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Sprint not found',
        ]);
});

// ============================================================================
// COMPLETE TESTS
// ============================================================================

test('owner can complete active sprint', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'active',
        'name' => 'Sprint 1',
    ]);

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}/complete", [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Sprint completed successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'completed_sprint' => [
                    'id',
                    'name',
                    'status',
                ],
                'next_sprint' => [
                    'id',
                    'name',
                    'status',
                ]
            ]
        ]);

    // Verify sprint was completed
    $sprint->refresh();
    expect($sprint->status)->toBe('completed');

    // Verify new sprint was created
    expect($workspace->sprints()->count())->toBe(2);

    $nextSprint = $workspace->sprints()->where('status', 'planned')->first();
    expect($nextSprint)->not->toBeNull();
    expect($nextSprint->name)->toBe('New Sprint');
});

test('completing sprint creates next sprint with correct duration', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'biweekly',
    ]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'active',
    ]);

    $this->patchJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}/complete", [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $nextSprint = $workspace->sprints()->where('status', 'planned')->first();

    expect($nextSprint->duration())->toBe(14);
});

test('cannot complete non-active sprint', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'completed',
    ]);

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}/complete", [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Only active sprints can be completed',
        ]);
});

test('cannot complete eternal sprint', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->eternal()->create([
        'workspace_id' => $workspace->id,
        'status' => 'active',
    ]);

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}/complete", [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Eternal sprints cannot be completed',
        ]);
});

test('non-member cannot complete sprint', function () {
    [$user, $token] = authenticatedUser();
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $sprint = Sprint::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'active',
    ]);

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/sprints/{$sprint->id}/complete", [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);
});
