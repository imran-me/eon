<?php
/* ============================================================
   EON · enter — the summit copy opens signed in.

   eon.gulfrabit.com is a demo copy of the ERP, not the production
   system, and the owner wants the stall QR code to land straight on
   the dashboard with no login screen. This boots the ERP's own
   Laravel, signs the browser in as its super-admin through the ERP's
   own session (so every screen and EON's write path behave exactly
   as for a real sign-in), then hands over to the workspace.

   No ERP file is edited: the route is registered here, at boot.
   Switched off by setting EON_DEMO_LOGIN=0 in the environment, or by
   deleting this file. embed/eon-inject.php sends /login here.
   ============================================================ */
declare(strict_types=1);

if (getenv('EON_DEMO_LOGIN') === '0') { http_response_code(404); exit; }

$erp = dirname(__DIR__) . '/erp';
require $erp . '/vendor/autoload.php';
$app = require $erp . '/bootstrap/app.php';

$to = (string) ($_GET['to'] ?? '');
if (!preg_match('~^/[A-Za-z0-9/_\-]*$~', $to)) $to = '';   // a path on this site, nothing else

$app->booted(function () use ($to) {
    \Illuminate\Support\Facades\Route::middleware('web')->get('eon/enter.php', function () use ($to) {
        $users = \App\Models\User::query()->orderBy('id')->get();
        $admin = $users->first(fn($u) => \Illuminate\Support\Str::slug((string) $u->getRoleNames()->first()) === 'super-admin');
        if (!$admin) return response('No super-admin user in this ERP copy.', 500);
        \Illuminate\Support\Facades\Auth::login($admin, true);
        session()->regenerate();
        session(['two_factor_verified' => true]);
        $dest = $to !== '' ? $to : '/super-admin/dashboard';
        return new \Illuminate\Http\RedirectResponse('/eon/workspace.html?to=' . rawurlencode($dest));
    });
});

// Laravel reads its base URL off the script name; present this as the ERP's own
// front controller so the session cookie and every URL are the ERP's, not /eon/enter.php's
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $erp . '/public/index.php';
chdir($erp . '/public');

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
