<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
         $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            // 'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout successful']);
    }


    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'email' => $user->email,
                        'token' => Hash::make($resetCode),
                    'created_at' => now(),
                ]
            );

            $appName = config('app.name', 'ERP');
            $htmlContent = "
                <h3>Password Reset Request</h3>
                <p>Hello {$user->name},</p>
                <p>We received a request to reset your password for {$appName}.</p>
                    <p><strong>Your 6-character reset code:</strong></p>
                    <p style=\"font-size:22px;letter-spacing:2px;font-weight:bold;\">{$resetCode}</p>
                    <p>This reset code will expire in 60 minutes.</p>
                <p>If you did not request this, you can safely ignore this email.</p>
            ";

            $mailResponse = sendBrevoMail(
                $user->email,
                $user->name,
                "{$appName} Password Reset Code",
                $htmlContent
            );

            if (!$mailResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send reset email. Please try again.',
                    'error' => $mailResponse->json() ?: $mailResponse->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                    'message' => 'Reset code sent to your email successfully.',
                'data' => [
                    'email' => $user->email,
                ],
            ], 200);
        } catch (Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to process forgot password request.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to reset password',
            'error' => __($status)
        ], 400);
    }

    /**
     * Change password for authenticated user.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
                'data' => ['user_id' => $user->id],
            ]);
        } catch (Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

}
