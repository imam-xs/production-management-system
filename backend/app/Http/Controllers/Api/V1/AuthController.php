<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\UserModel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// plain Sanctum personal-access-token auth — no stateful/cookie flow
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        /** @var UserModel|null $user */
        $user = UserModel::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw new AuthenticationException('These credentials do not match our records.');
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()->delete();

        return $this->message('Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->data(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
