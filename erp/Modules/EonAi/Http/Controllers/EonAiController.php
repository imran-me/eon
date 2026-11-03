<?php

namespace Modules\EonAi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\EonAi\Support\Connection;
use Modules\EonAi\Support\Handshake;

/**
 * EON AI pages: home (/eon-ai) and the connection point (/eon-ai/connection).
 * Super admin only — checked on every entry point, not on the route: a route
 * is a door, the lock belongs on the room.
 */
class EonAiController extends Controller
{
    private function guard(): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->is_super_admin || $user->hasRole('super admin')), 403);
    }

    public function index(Request $request, $role)
    {
        $this->guard();

        return view('eonai::index', [
            'role'      => $role,
            'installed' => Connection::installed(),
            'row'       => Connection::row(),
            'hasKey'    => (bool) Connection::secret(),
        ]);
    }

    public function connection(Request $request, $role)
    {
        $this->guard();

        return view('eonai::connection', [
            'role'      => $role,
            'installed' => Connection::installed(),
            'row'       => Connection::row(),
            'masked'    => Connection::maskedSecret(),
            'path'      => Handshake::PATH,
        ]);
    }

    /** Address + on/off. Changing the address clears the old test result. */
    public function save(Request $request, $role)
    {
        $this->guard();
        abort_unless(Connection::installed(), 409, 'EON AI is not installed on this server yet (migration pending).');

        $data = $request->validate([
            'server_url' => 'nullable|string|max:255',
            'enabled'    => 'nullable|boolean',
        ]);
        $url = trim((string) ($data['server_url'] ?? ''));
        $url = $url === '' ? null : rtrim($url, '/');

        if ($url && ($reason = Handshake::refuse($url))) {
            return back()->withInput()->with('eai_error', $reason);
        }

        $row = Connection::row();
        $fields = ['server_url' => $url, 'enabled' => (bool) ($data['enabled'] ?? false)];
        if (! $row || $row->server_url !== $url) {
            $fields += ['last_tested_at' => null, 'last_test_ok' => null, 'last_test_message' => null, 'last_test_ms' => null, 'eon_version' => null];
        }
        Connection::save($fields);

        return redirect()->route('role.eon-ai.connection', ['role' => $role])->with('eai_ok', 'Saved.');
    }

    /**
     * The shared key: generate a new one (shown in full ONCE, to copy into
     * EON), or paste the one EON already uses.
     */
    public function key(Request $request, $role)
    {
        $this->guard();
        abort_unless(Connection::installed(), 409, 'EON AI is not installed on this server yet (migration pending).');

        if ($request->input('mode') === 'paste') {
            $data = $request->validate(['key' => 'required|string|min:32|max:200|regex:/^\S+$/']);
            Connection::storeSecret($data['key']);
            Connection::save(['last_test_ok' => null, 'last_test_message' => null]);

            return redirect()->route('role.eon-ai.connection', ['role' => $role])->with('eai_ok', 'Key saved.');
        }

        $key = bin2hex(random_bytes(32));
        Connection::storeSecret($key);
        Connection::save(['last_test_ok' => null, 'last_test_message' => null]);

        return redirect()->route('role.eon-ai.connection', ['role' => $role])
            ->with('eai_ok', 'New key generated. Copy it into EON now — it is shown only this once.')
            ->with('eai_new_key', $key);
    }

    public function test(Request $request, $role)
    {
        $this->guard();
        abort_unless(Connection::installed(), 409, 'EON AI is not installed on this server yet (migration pending).');

        $row = Connection::row();
        $result = Handshake::run($row->server_url ?? null, Connection::secret());

        Connection::save([
            'last_tested_at'    => now(),
            'last_test_ok'      => $result['ok'],
            'last_test_message' => mb_substr($result['message'], 0, 500),
            'last_test_ms'      => $result['ms'],
            'eon_version'       => $result['version'],
        ]);

        return redirect()->route('role.eon-ai.connection', ['role' => $role])
            ->with($result['ok'] ? 'eai_ok' : 'eai_error', $result['message']);
    }
}
