<?php
// filepath: c:\laragon\www\laravel-app\laravel-app\app\Http\Controllers\API\RegisterController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Register;
use App\Models\RegisterApiKey;

class RegisterController extends Controller
{
    public function updateStatus(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);
        
        // Update register status
        if ($register) {
            $register->last_activity = now();
            if ($request->has('status')) {
                $register->status = $request->status;
            }
            $register->save();
        }
        
        return response()->json(['success' => true]);
    }
    
    public function getSettings(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);
        
        return response()->json([
            'register' => [
                'id' => $register->id,
                'name' => $register->name,
                'register_number' => $register->register_number,
                'settings' => $register->settings,
            ]
        ]);
    }
    
    public function heartbeat(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);
        
        $register->last_activity = now();
        $register->save();
        
        return response()->json(['success' => true]);
    }
}