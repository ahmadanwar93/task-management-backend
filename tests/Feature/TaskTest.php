<?php

use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;


test('owner can create task', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');
    $workspace->initializeSprint();

    $taskData = [
        'title' => 'Fix login bug',
        'description' => 'Users cannot login',
        'status' => 'todo',
    ];

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/tasks", $taskData, [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Task created successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'title',
                'description',
                'status',
                'workspace_id',
                'sprint_id',
                'created_by',
                'created_at',
            ]
        ]);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Fix login bug',
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
    ]);
});

test('guest cannot create task', function () {
    $owner = User::factory()->create();
    [$guest, $token] = authenticatedUser();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/tasks", [
        'title' => 'Test task',
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertForbidden();
});

test('task auto-assigns to active sprint when sprint_id not provided', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');
    $workspace->initializeSprint();

    $activeSprint = $workspace->activeSprint;

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/tasks", [
        'title' => 'Test task',
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(201);

    expect($response->json('data.sprint_id'))->toBe($activeSprint->id);
});

test('task can be assigned to workspace member', function () {
    [$owner, $token] = authenticatedUser();
    $member = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($member, 'guest');
    $workspace->initializeSprint();
    $response = $this->postJson("/api/workspaces/{$workspace->slug}/tasks", [
        'title' => 'Assigned task',
        'assigned_to' => $member->id,
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(201);
    expect($response->json('data.assigned_to.id'))->toBe($member->id);
});

test('can list all tasks in workspace', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    Task::factory()->count(5)->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

test('can filter tasks by sprint', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $sprint1 = Sprint::factory()->create(['workspace_id' => $workspace->id]);
    $sprint2 = Sprint::factory()->create(['workspace_id' => $workspace->id]);

    Task::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'sprint_id' => $sprint1->id,
        'created_by' => $user->id,
    ]);

    Task::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'sprint_id' => $sprint2->id,
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks?sprint_id={$sprint1->id}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter tasks by status', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    Task::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'status' => 'todo',
        'created_by' => $user->id,
    ]);

    Task::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'status' => 'in_progress',
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks?status=in_progress", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter tasks by assignee', function () {
    [$user, $token] = authenticatedUser();
    $otherUser = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    Task::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'assigned_to' => $user->id,
        'created_by' => $user->id,
    ]);

    Task::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'assigned_to' => $otherUser->id,
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks?assigned_to={$user->id}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can filter my tasks only', function () {
    [$user, $token] = authenticatedUser();
    $otherUser = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    Task::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'assigned_to' => $user->id,
        'created_by' => $user->id,
    ]);

    Task::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'assigned_to' => $otherUser->id,
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks?assigned_to=me", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can filter backlog tasks', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $sprint = Sprint::factory()->create(['workspace_id' => $workspace->id]);

    Task::factory()->count(3)->backlog()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
    ]);

    Task::factory()->count(2)->create([
        'workspace_id' => $workspace->id,
        'sprint_id' => $sprint->id,
        'created_by' => $user->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks?backlog=true", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('guest can view tasks', function () {
    $owner = User::factory()->create();
    [$guest, $token] = authenticatedUser();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    Task::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
    ]);

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('non-member cannot list tasks', function () {
    [$user, $token] = authenticatedUser();
    $owner = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->addMember($owner, 'owner');

    $response = $this->getJson("/api/workspaces/{$workspace->slug}/tasks", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertNotFound();
});

test('owner can move task to different status', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'todo',
        'created_by' => $user->id,
    ]);

    $response = $this->patchJson(
        "/api/workspaces/{$workspace->slug}/tasks/{$task->id}/move",
        ['status' => 'in_progress'],
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Task moved successfully',
        ]);

    $task->refresh();
    expect($task->status)->toBe('in_progress');
});

test('moving task to done sets completed_at', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'in_progress',
        'completed_at' => null,
        'created_by' => $user->id,
    ]);

    $response = $this->patchJson(
        "/api/workspaces/{$workspace->slug}/tasks/{$task->id}/move",
        ['status' => 'done'],
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertOk();

    $task->refresh();
    expect($task->status)->toBe('done');
    expect($task->completed_at)->not->toBeNull();
});

test('moving task from done clears completed_at', function () {
    [$user, $token] = authenticatedUser();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addMember($user, 'owner');

    $task = Task::factory()->completed()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
    ]);

    expect($task->completed_at)->not->toBeNull();

    $response = $this->patchJson(
        "/api/workspaces/{$workspace->slug}/tasks/{$task->id}/move",
        ['status' => 'todo'],
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertOk();

    $task->refresh();
    expect($task->status)->toBe('todo');
    expect($task->completed_at)->toBeNull();
});

test('guest can move own task', function () {
    $owner = User::factory()->create();
    [$guest, $token] = authenticatedUser();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'todo',
        'created_by' => $guest->id,
        'assigned_to' => $guest->id
    ]);

    $response = $this->patchJson(
        "/api/workspaces/{$workspace->slug}/tasks/{$task->id}/move",
        ['status' => 'in_progress'],
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertOk();

    $task->refresh();
    expect($task->status)->toBe('in_progress');
});

test('guest cannot move others task', function () {
    $owner = User::factory()->create();
    [$guest, $token] = authenticatedUser();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => 'todo',
        'created_by' => $owner->id,
        'assigned_to' => $owner->id
    ]);

    $response = $this->patchJson(
        "/api/workspaces/{$workspace->slug}/tasks/{$task->id}/move",
        ['status' => 'in_progress'],
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertForbidden();
});
