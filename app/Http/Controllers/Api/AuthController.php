<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoginUser;
use App\Http\Requests\StoreRegisterUser;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(StoreRegisterUser $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ],
            'User registered successfully',
            201
        );
    }

    public function login(StoreLoginUser $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // to protect against timing attack
            $dummyHash = '$2y$12$ejmtBtTNyX7iVInZzYEwG.tSS5Qxhq/iR36lI6kMcewoNlJKTk16C';
            Hash::check($validated['password'], $dummyHash);
            return $this->errorResponse(null, "The provided credentials are incorrect.", 401);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse(null, "The provided credentials are incorrect.", 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ],
            'User logged in successfully',
            200
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            null,
            'User logged out successfully',
            200
        );
    }
}
