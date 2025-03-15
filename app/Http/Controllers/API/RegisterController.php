<?php
// filepath: c:\laragon\www\laravel-app\laravel-app\app\Http\Controllers\API\RegisterController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Register;
use App\Models\RegisterApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    /**
     * Update the register status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        // Update register status
        $register->last_activity = now();
        if ($request->has('status')) {
            $register->status = $request->status;
        }
        $register->save();

        return response()->json(['success' => true]);
    }

    /**
     * Get the register settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettings(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        return response()->json([
            'register' => [
                'id' => $register->id,
                'name' => $register->name,
                'register_number' => $register->register_number,
                'settings' => $register->settings ?? [],
                'current_cash_amount' => (float) $register->current_cash_amount,
                'opening_amount' => (float) $register->opening_amount,
            ]
        ]);
    }

    /**
     * Get transactions for this register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        // Get date filter or default to today
        $date = $request->input('date', today()->toDateString());
        
        $transactions = $register->transactions()
            ->whereDate('created_at', $date)
            ->with(['user:id,name', 'customer:id,name,phone', 'items'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json($transactions);
    }

    /**
     * Update cash amount in register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCashAmount(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:add,remove,set',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update cash amount based on type
        $oldAmount = $register->current_cash_amount;

        switch ($request->type) {
            case 'add':
                $register->current_cash_amount += $request->amount;
                break;
            case 'remove':
                $register->current_cash_amount = max(0, $register->current_cash_amount - $request->amount);
                break;
            case 'set':
                $register->current_cash_amount = $request->amount;
                break;
        }

        $register->save();

        return response()->json([
            'success' => true,
            'data' => [
                'old_amount' => $oldAmount,
                'new_amount' => $register->current_cash_amount,
            ]
        ]);
    }

    /**
     * Log heartbeat from register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function heartbeat(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        // Update last activity
        $register->last_activity = now();
        $register->save();

        return response()->json(['success' => true]);
    }

    /**
     * Login to a register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'pin' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        // Handle user login here
        $user = User::find($request->user_id);

        $register->current_user_id = $user->id;
        $register->session_started_at = now();
        $register->session_transaction_count = 0;
        $register->session_revenue = 0;
        $register->save();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ]
        ]);
    }

    /**
     * Logout from register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        $register->current_user_id = null;
        $register->session_started_at = null;
        $register->save();

        return response()->json(['success' => true]);
    }

    /**
     * Debug authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugAuth(Request $request)
    {
        Log::debug('Auth debug', [
            'auth_header' => $request->header('Authorization'),
            'api_key' => $request->header('X-API-KEY'),
            'user' => auth('register')->user(),
            'all_headers' => $request->headers->all(),
        ]);
        
        if (auth('register')->check()) {
            return response()->json([
                'authenticated' => true,
                'user' => auth('register')->user(),
            ]);
        }
        
        return response()->json([
            'authenticated' => false,
            'header' => $request->header('Authorization'),
        ], 401);
    }
}