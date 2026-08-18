<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str; // Ensure you import Str for slugging roles
class LoginController extends Controller
{
    public function showLoginForm()
    {
        // Check if the user is already authenticated
        if (Auth::check()) {
            $role = Str::slug(Auth::user()->getRoleNames()->first()); // e.g., "super-admin"
            if ($role) {
                return redirect()->route('role.dashboard', ['role' => $role]);
            } else {
                Auth::logout();
                return redirect()->route('login')->withErrors(['email' => 'Role not assigned']);
            }
        }
        // If not authenticated, show the login form

        return view('auth.login'); // your custom view
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            $role = Str::slug($user->getRoleNames()->first());

            if (!$role) {
                Auth::logout();
                return redirect()->route('login')->withErrors(['email' => 'Role not assigned']);
            }

            // If 2FA is enabled, don't fully log in yet
            if ($user->two_factor_confirmed_at) {
                Auth::logout();
                session(['two_factor_user_id' => $user->id]);
                return redirect()->route('two-factor.verify');
            }

            return redirect()->route('role.dashboard', ['role' => $role]);
        }

        return redirect()->route('login')->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('login');
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.'
            ]);
        }

        try {
            Auth::user()->update([
                'password' => Hash::make($request->new_password)
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.'
        ]);
    }
}
