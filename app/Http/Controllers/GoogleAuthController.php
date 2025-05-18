<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;

class GoogleAuthController extends Controller
{
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        // Verify Google token
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->token,
        ])->get('https://www.googleapis.com/oauth2/v1/userinfo?alt=json');

        if (!$response->ok()) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $data = $response->json();
        logger()->info('Google User Data: ', $data);

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'] ?? $data['email'],
                'password' => bcrypt(Str::random(24)), // Not used
                'email_verified_at' => now(),
            ]
        );

        $token = $user->createToken('google_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}
