<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class SsoController extends Controller
{
    // ERP → DM Portal redirect
    // Logged-in user এর জন্য one-time token তৈরি করে DM Portal এ পাঠায়
    public function redirect(Request $request)
    {
        $user = auth()->user();

        $token = Str::random(64);
        

        Cache::put('sso_token_' . $token, [
            'email'      => $user->email,
            'name'       => $user->name,
            'username'   => $user->username,
            'phone'      => $user->phone,
            'company_id' => $user->company_id,
        ], now()->addMinutes(2));

        $dmPortalUrl = rtrim(config('services.sso.dm_portal'), '/');

        return redirect($dmPortalUrl . '/sso/login?token=' . $token);
    }

    // DM Portal API call এ token verify করে user info return করে
    // এই endpoint টা DM Portal থেকে call হবে
    public function verify(Request $request)
    {
        
        try {
            if ($request->header('X-SSO-Secret') !== config('services.sso.secret')) {
                return response()->json(['error' => 'Unauthorized', 'debug' => 'secret mismatch'], 401);
            }

            $token = $request->query('token');
            

            if (!$token) {
                return response()->json(['error' => 'Token missing'], 422);
            }

            $data = Cache::pull('sso_token_' . $token);

            if (!$data) {
                return response()->json(['error' => 'Invalid or expired token'], 401);
            }

            return response()->json(['user' => $data]);

        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Server error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }
}
