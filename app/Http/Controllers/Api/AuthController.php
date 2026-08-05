<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data);

        return response()->json($this->issueToken($user, 'registration'), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        return response()->json($this->issueToken($user, 'login'));
    }

    public function logout(Request $request): JsonResponse
    {
        $plainToken = $request->bearerToken();

        if ($plainToken) {
            $request->user()->apiTokens()
                ->where('token_hash', hash('sha256', $plainToken))
                ->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    private function issueToken(User $user, string $name): array
    {
        $plainToken = Str::random(64);

        $user->apiTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return [
            'token' => $plainToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ];
    }
}
