<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // Show QR code setup page
    public function setup()
    {
        $user = Auth::user();

        // If already confirmed, show the manage page (with disable option)
        if ($user->two_factor_confirmed_at) {
            return view('auth.two-factor.setup', ['qrCodeSvg' => null, 'secret' => null]);
        }

        // Generate a new secret if not set yet
        if (!$user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->update(['two_factor_secret' => $secret]);
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return view('auth.two-factor.setup', [
            'qrCodeSvg' => $qrCodeSvg,
            'secret'    => $user->two_factor_secret,
        ]);
    }

    // Enable 2FA after OTP confirmation
    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $user = Auth::user();

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid OTP code. Please try again.']);
        }

        $user->update(['two_factor_confirmed_at' => now()]);

        return redirect()->route('role.dashboard', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])->with('success', '2FA has been enabled successfully.');
    }

    // Disable 2FA
    public function disable(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $user = Auth::user();

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid OTP code. Cannot disable 2FA.']);
        }

        $user->update([
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->back()->with('success', '2FA has been disabled.');
    }

    // Show OTP verify page (after login)
    public function showVerify()
    {
        if (!session('two_factor_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor.verify');
    }

    // Verify OTP after login
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $userId = session('two_factor_user_id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::findOrFail($userId);

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid OTP code. Please try again.']);
        }

        // Clear temp session and log the user in
        session()->forget('two_factor_user_id');
        session(['two_factor_verified' => true]);

        Auth::login($user);

        $role = \Illuminate\Support\Str::slug($user->getRoleNames()->first());

        return redirect()->route('role.dashboard', ['role' => $role]);
    }
}
