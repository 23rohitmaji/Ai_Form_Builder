<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        try {
            $this->ensureDatabaseReady();

            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:190', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            $user = User::create($data);

            return response()->json($this->issueToken($user, 'registration'), 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->authFailure($exception);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $this->ensureDatabaseReady();

            $data = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $user = User::where('email', $data['email'])->first();

            if (! $user || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['message' => 'Invalid credentials.'], 422);
            }

            return response()->json($this->issueToken($user, 'login'));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->authFailure($exception);
        }
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

    private function ensureDatabaseReady(): void
    {
        if (app()->environment('production') && config('database.default') === 'sqlite') {
            throw new \RuntimeException('Production DB is still sqlite. Set DB_CONNECTION=mysql and Aiven DB variables in Vercel.');
        }

        DB::connection()->getPdo();

        if (! Schema::hasTable('users') || ! Schema::hasTable('api_tokens')) {
            Artisan::call('migrate', ['--force' => true]);
        }
    }

    private function authFailure(Throwable $exception): JsonResponse
    {
        report($exception);

        return response()->json([
            'message' => 'Auth service failed.',
            'error' => class_basename($exception),
            'detail' => $exception->getMessage(),
        ], 500);
    }
}
