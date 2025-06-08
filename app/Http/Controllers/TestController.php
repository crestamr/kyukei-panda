<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * Test if the user issue is fixed.
     */
    public function testUserFix(): JsonResponse
    {
        try {
            // Test the same logic as PandaDashboardController
            $user = User::first();
            
            if (!$user) {
                return response()->json([
                    'status' => 'no_users',
                    'message' => 'No users found in database',
                    'users_count' => User::count(),
                ]);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'User found successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'users_count' => User::count(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ]);
        }
    }
}
