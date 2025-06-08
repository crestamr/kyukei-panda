<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Generate API token for external applications.
     */
    public function generateToken(Request $request): JsonResponse
    {
        $key = 'api-token-generation:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => 'Too many token generation attempts. Try again in ' . $seconds . ' seconds.',
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string',
            'token_name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string|in:activity:create,activity:read,status:read,project:assign,suggestions:read',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Default abilities if none specified
        $abilities = $request->abilities ?? [
            'activity:create',
            'activity:read',
            'status:read',
            'project:assign',
            'suggestions:read'
        ];

        $token = $user->createToken($request->token_name, $abilities);

        return response()->json([
            'success' => true,
            'message' => 'API token generated successfully',
            'data' => [
                'token' => $token->plainTextToken,
                'token_name' => $request->token_name,
                'abilities' => $abilities,
                'expires_at' => null, // Tokens don't expire by default
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]
        ], 201);
    }

    /**
     * Validate API token and return user info.
     */
    public function validateToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toISOString(),
                ]
            ]
        ]);
    }
}
