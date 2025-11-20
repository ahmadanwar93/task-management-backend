<?php

use App\Models\User;
use App\Models\Workspace;

test('authenticated user can create workspace with sprint enabled', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspaceData = [
        'name' => 'My Project',
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ];

    $response = $this->postJson('/api/workspaces', $workspaceData, [
        'Authorization' => 'Bearer ' . $token
    ]);
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Workspace created successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'slug',
                'sprint_enabled',
                'sprint_duration',
                'created_at'
            ]
        ]);

    $this->assertDatabaseHas('workspaces', [
        'name' => 'My Project',
        'owner_id' => $user->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ]);

    $workspace = Workspace::where('name', 'My Project')->first();
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    expect($workspace->slug)->toHaveLength(10);
});

test('authenticated user can create workspace without sprint', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspaceData = [
        'name' => 'Simple Kanban',
        'sprint_enabled' => false,
    ];

    $response = $this->postJson('/api/workspaces', $workspaceData, [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Workspace created successfully',
        ]);

    $this->assertDatabaseHas('workspaces', [
        'name' => 'Simple Kanban',
        'owner_id' => $user->id,
        'sprint_enabled' => false,
        'sprint_duration' => null,
    ]);

    $workspace = Workspace::where('name', 'Simple Kanban')->first();
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

test('workspace creation fails when name is missing', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspaceData = [
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ];

    $response = $this->postJson('/api/workspaces', $workspaceData, [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['name']
        ]);

    $this->assertDatabaseCount('workspaces', 0);
});

test('workspace creation fails when sprint enabled but duration missing', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspaceData = [
        'name' => 'My Project',
        'sprint_enabled' => true,
    ];

    $response = $this->postJson('/api/workspaces', $workspaceData, [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['sprint_duration']
        ]);

    $this->assertDatabaseCount('workspaces', 0);
});

test('workspace creation fails when sprint duration is invalid', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspaceData = [
        'name' => 'My Project',
        'sprint_enabled' => true,
        'sprint_duration' => 'monthly', // invalid value
    ];

    $response = $this->postJson('/api/workspaces', $workspaceData, [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['sprint_duration']
        ]);

    $this->assertDatabaseCount('workspaces', 0);
});

test('unauthenticated user cannot create workspace', function () {
    $workspaceData = [
        'name' => 'My Project',
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ];

    $response = $this->postJson('/api/workspaces', $workspaceData);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthenticated.',
        ]);

    $this->assertDatabaseCount('workspaces', 0);
});

test('authenticated user can list their workspaces', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace1 = Workspace::create([
        'name' => 'Project 1',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly'
    ]);
    $workspace1->addMember($user, 'owner');

    $workspace2 = Workspace::create([
        'name' => 'Project 2',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => false,
    ]);
    $workspace2->addMember($user, 'owner');

    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'Other Project',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $otherUser->id,
        'sprint_enabled' => false,
    ]);
    $otherWorkspace->addMember($otherUser, 'owner');

    $response = $this->getJson('/api/workspaces', [
        'Authorization' => 'Bearer ' . $token
    ]);
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Workspaces retrieved successfully',
        ])
        ->assertJsonCount(2, 'data'); // Only user's 2 workspaces

    expect($response->json('data.0.is_owner'))->toBeTrue();
    expect($response->json('data.1.is_owner'))->toBeTrue();
});

test('authenticated user can get workspace details by slug', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'My Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ]);
    $workspace->addMember($user, 'owner');

    $response = $this->getJson("/api/workspaces/{$workspace->slug}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Workspace retrieved successfully',
            'data' => [
                'id' => $workspace->id,
                'name' => 'My Workspace',
                'slug' => $workspace->slug,
                'is_owner' => true,
                'sprint_enabled' => true,
                'sprint_duration' => 'weekly',
            ]
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'slug',
                'is_owner',
                'sprint_enabled',
                'sprint_duration',
                'created_at',
                'updated_at',
                'owner' => ['id', 'name', 'email'],
                'members' => [
                    '*' => ['id', 'name', 'email', 'role', 'joined_at']
                ]
            ]
        ]);
});

test('user cannot access workspace they are not member of', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $otherUser = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Private Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $otherUser->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($otherUser, 'owner');

    $response = $this->getJson("/api/workspaces/{$workspace->slug}", [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);
});

test('guest member can view workspace but is_owner is false', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $guestToken = $guest->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Shared Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'biweekly',
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $response = $this->getJson("/api/workspaces/{$workspace->slug}", [
        'Authorization' => 'Bearer ' . $guestToken
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Shared Workspace',
                'is_owner' => false, // Guest is not owner
            ]
        ]);

    expect($response->json('data.members'))->toHaveCount(2);
});

test('unauthenticated user cannot list workspaces', function () {
    $response = $this->getJson('/api/workspaces');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthenticated.',
        ]);
});

test('owner can update workspace name', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Original Name',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($user, 'owner');

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/settings", [
        'name' => 'Updated Name',
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Workspace updated successfully',
            'data' => [
                'name' => 'Updated Name',
            ]
        ]);

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Updated Name',
    ]);
});

test('owner can enable sprint mode with duration', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'My Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($user, 'owner');

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/settings", [
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'sprint_enabled' => true,
                'sprint_duration' => 'weekly',
            ]
        ]);

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ]);
});

test('owner can disable sprint mode', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'My Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => true,
        'sprint_duration' => 'weekly',
    ]);
    $workspace->addMember($user, 'owner');

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/settings", [
        'sprint_enabled' => false,
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'sprint_enabled' => false,
                'sprint_duration' => null, // Should clear duration
            ]
        ]);

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'sprint_enabled' => false,
        'sprint_duration' => null,
    ]);
});
test('update fails when enabling sprint without duration', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'My Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($user, 'owner');

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/settings", [
        'sprint_enabled' => true,
        // missing sprint_duration
    ], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['sprint_duration']
        ]);
});

test('guest cannot update workspace', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $guestToken = $guest->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/settings", [
        'name' => 'Hacked Name',
    ], [
        'Authorization' => 'Bearer ' . $guestToken
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => "This action is unauthorized.",
        ]);

    // Verify name wasn't changed
    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Workspace',
    ]);
});

test('owner can delete workspace', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'To Be Deleted',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $user->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($user, 'owner');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}", [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Workspace deleted successfully',
        ]);

    // Verify workspace is deleted
    $this->assertDatabaseMissing('workspaces', [
        'id' => $workspace->id,
    ]);

    // Verify pivot entries are also deleted (cascade)
    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
    ]);
});

test('guest cannot delete workspace', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $guestToken = $guest->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Protected Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}", [], [
        'Authorization' => 'Bearer ' . $guestToken
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'This action is unauthorized.',
        ]);

    // Verify workspace still exists
    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Protected Workspace',
    ]);
});

test('non-member cannot update workspace', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $outsiderToken = $outsider->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Private Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->patchJson("/api/workspaces/{$workspace->slug}/settings", [
        'name' => 'Hacked',
    ], [
        'Authorization' => 'Bearer ' . $outsiderToken
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);
});

test('non-member cannot delete workspace', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $outsiderToken = $outsider->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Private Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}", [], [
        'Authorization' => 'Bearer ' . $outsiderToken
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
    ]);
});

test('owner can invite a user to workspace', function () {
    $owner = User::factory()->create();
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $userToInvite = User::factory()->create([
        'email' => 'guest@example.com',
    ]);

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/invite", [
        'email' => 'guest@example.com',
    ], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Member invited successfully',
            'data' => null
        ]);

    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $userToInvite->id,
        'role' => 'guest',
    ]);
});

test('invite fails if user does not exist', function () {
    $owner = User::factory()->create();
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/invite", [
        'email' => 'nonexistent@example.com',
    ], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['email']
        ]);
});

test('invite fails if user is already a member', function () {
    $owner = User::factory()->create();
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $existingMember = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($existingMember, 'guest');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/invite", [
        'email' => 'existing@example.com',
    ], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'This user is already a member of this workspace',
        ]);
});

test('owner cannot invite themselves', function () {
    $owner = User::factory()->create([
        'email' => 'owner@example.com',
    ]);
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/invite", [
        'email' => 'owner@example.com',
    ], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'This user is already a member of this workspace',
        ]);
});

test('guest cannot invite members', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $guestToken = $guest->createToken('auth_token')->plainTextToken;

    $userToInvite = User::factory()->create([
        'email' => 'newuser@example.com',
    ]);

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/invite", [
        'email' => 'newuser@example.com',
    ], [
        'Authorization' => 'Bearer ' . $guestToken
    ]);

    $response->assertStatus(403);

    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $userToInvite->id,
    ]);
});

test('owner can remove a member from workspace', function () {
    $owner = User::factory()->create();
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $member = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($member, 'guest');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}/members/{$member->id}", [], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);

    // Verify member was removed from pivot table
    $this->assertDatabaseMissing('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
});

test('cannot remove workspace owner', function () {
    $owner = User::factory()->create();
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}/members/{$owner->id}", [], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot remove the workspace owner',
        ]);

    // Verify owner still exists in workspace
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);
});

test('cannot remove user who is not a member', function () {
    $owner = User::factory()->create();
    $ownerToken = $owner->createToken('auth_token')->plainTextToken;

    $nonMember = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}/members/{$nonMember->id}", [], [
        'Authorization' => 'Bearer ' . $ownerToken
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'User is not a member of this workspace.',
        ]);
});

test('guest cannot remove members', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $guestToken = $guest->createToken('auth_token')->plainTextToken;

    $anotherMember = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($guest, 'guest');
    $workspace->addMember($anotherMember, 'guest');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}/members/{$anotherMember->id}", [], [
        'Authorization' => 'Bearer ' . $guestToken
    ]);

    $response->assertStatus(403);

    // Verify member was NOT removed
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $anotherMember->id,
    ]);
});

test('non-member cannot invite to workspace', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $outsiderToken = $outsider->createToken('auth_token')->plainTextToken;

    $userToInvite = User::factory()->create([
        'email' => 'newuser@example.com',
    ]);

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');

    $response = $this->postJson("/api/workspaces/{$workspace->slug}/invite", [
        'email' => 'newuser@example.com',
    ], [
        'Authorization' => 'Bearer ' . $outsiderToken
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);
});

test('non-member cannot remove members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $outsiderToken = $outsider->createToken('auth_token')->plainTextToken;

    $workspace = Workspace::create([
        'name' => 'Team Workspace',
        'slug' => Workspace::generateUniqueSlug(),
        'owner_id' => $owner->id,
        'sprint_enabled' => false,
    ]);
    $workspace->addMember($owner, 'owner');
    $workspace->addMember($member, 'guest');

    $response = $this->deleteJson("/api/workspaces/{$workspace->slug}/members/{$member->id}", [], [
        'Authorization' => 'Bearer ' . $outsiderToken
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Workspace not found',
        ]);

    // Verify member still exists
    $this->assertDatabaseHas('workspace_user', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
});
