<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushTokenController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $token = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => (int) Auth::id(),
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Push token registered.',
            'data' => [
                'id' => $token->id,
            ],
        ]);
    }

    public function unregister(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $deleted = DeviceToken::query()
            ->where('token', $validated['token'])
            ->where('user_id', (int) Auth::id())
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Push token removed.',
            'data' => [
                'deleted' => (int) $deleted,
            ],
        ]);
    }
}
