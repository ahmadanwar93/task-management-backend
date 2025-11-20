<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user can register with valid data', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/register', $userData);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'User registered successfully',
            // here we cannot check the data.token and data.token.user.id since it is generated automatically
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'user' => ['id', 'name', 'email']
            ]
        ]);

    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    expect($user->password)->not->toBe('password123');
});

test('registration fails when password confirmation does not match', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'differentpassword',
    ];

    $response = $this->postJson('/api/register', $userData);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['password']
        ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'john@example.com',
    ]);
});

test('user can login with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'User logged in successfully',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'user' => ['id', 'name', 'email']
            ]
        ]);
});

test('login fails with incorrect password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('login fails with non-existent email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->postJson('/api/logout', [], [
        'Authorization' => 'Bearer ' . $token
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'User logged out successfully',
        ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

test('logout fails without authentication token', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Unauthenticated.',
        ]);
});
