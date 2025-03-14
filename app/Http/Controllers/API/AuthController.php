<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        // Create a Sanctum token
        $token = $user->createToken('api-test-token')->plainTextToken;
        
        // Generate a device identifier based on input and user-agent
        $deviceName = $request->device_name ?? 'Unknown Device';
        $deviceInfo = $request->userAgent() ?? 'Unknown';
        $deviceIdentifier = md5($deviceName . '_' . $deviceInfo . '_' . $user->id);
        
        // Find existing API key for this device or create a new one
        $apiKey = ApiKey::firstOrNew([
            'user_id' => $user->id,
            'device_identifier' => $deviceIdentifier,
        ]);

        // Only generate a new key if this is a new record
        if (!$apiKey->exists) {
            $apiKey->fill([
                'name' => $deviceName,
                'key' => ApiKey::generateKey(),
                'device_info' => $deviceInfo,
                'is_active' => true,
            ]);
        }
        
        // Always update the last used timestamp
        $apiKey->last_used_at = now();
        $apiKey->save();
        
        return response()->json([
            'user' => $user,
            'token' => $token,
            'api_key' => $apiKey->key
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
