<?php

namespace App\Http\Controllers;

use App\Models\UserShortcut;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The two JSON endpoints behind the right-click "Add Shortcut Tab" /
 * "Add to Shortcut Menu" options (layout/shortcuts.blade.php).
 *
 * Every query here is scoped to the signed-in user. A shortcut is personal,
 * so there is no permission to check beyond being logged in — the {role}
 * group's middleware has already done that.
 *
 * Both actions catch QueryException: until the user_shortcuts migration has
 * run on a server, the shell renders with no shortcuts (user_shortcuts()
 * swallows the failure) and these endpoints answer with a plain message
 * instead of a raw SQL error in the toast.
 */
class UserShortcutController extends Controller
{
    private const NOT_INSTALLED = 'Shortcuts are not installed on this server yet.';

    /**
     * Pin a page. Pinning a page that is already pinned as the same kind
     * returns the existing row (200, existed: true) instead of a 422 — the
     * browser treats both the same way, so a double right-click is harmless.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind'  => ['required', 'string', 'in:' . implode(',', UserShortcut::KINDS)],
            'label' => ['required', 'string', 'max:60'],
            // A same-origin PATH only: must start with a single "/", nothing
            // that could read as a scheme or a protocol-relative host.
            'url'   => ['required', 'string', 'max:500', 'regex:#^/(?!/)[^\s<>"\'\\\\]*$#'],
            // A class list like "fas fa-chart-line" or "bi bi-water".
            'icon'  => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9 \-]+$/'],
        ]);

        $userId = (int) $request->user()->id;
        $kind   = $data['kind'];
        $label  = Str::limit(trim(preg_replace('/\s+/', ' ', $data['label'])), 60, '');
        $url    = $data['url'];
        $icon   = isset($data['icon']) ? trim($data['icon']) : null;

        if ($label === '') {
            return response()->json(['ok' => false, 'message' => 'That link has no name to pin.'], 422);
        }

        try {
            $existing = UserShortcut::forUser($userId)->ofKind($kind)->where('url', $url)->first();
            if ($existing) {
                return response()->json(['ok' => true, 'existed' => true, 'shortcut' => $existing->toClient()]);
            }

            $cap   = UserShortcut::MAX_PER_KIND[$kind];
            $count = UserShortcut::forUser($userId)->ofKind($kind)->count();
            if ($count >= $cap) {
                $where = $kind === UserShortcut::KIND_TAB ? 'shortcut tabs' : 'the shortcut menu';
                return response()->json([
                    'ok'      => false,
                    'message' => "You already have {$cap} {$where}. Remove one to add another.",
                ], 422);
            }

            $nextOrder = (int) UserShortcut::forUser($userId)->ofKind($kind)->max('sort_order') + 1;

            $shortcut = UserShortcut::create([
                'user_id'    => $userId,
                'kind'       => $kind,
                'label'      => $label,
                'url'        => $url,
                'icon'       => $icon ?: null,
                'sort_order' => $nextOrder,
            ]);
        } catch (QueryException $e) {
            return response()->json(['ok' => false, 'message' => self::NOT_INSTALLED], 503);
        }

        return response()->json(['ok' => true, 'existed' => false, 'shortcut' => $shortcut->toClient()], 201);
    }

    /** Unpin. Scoped to the user's own rows: somebody else's id is a 404, never a delete. */
    public function destroy(Request $request, string $role, int $shortcut): JsonResponse
    {
        try {
            $row = UserShortcut::forUser((int) $request->user()->id)->find($shortcut);

            if (! $row) {
                return response()->json(['ok' => false, 'message' => 'That shortcut is already gone.'], 404);
            }

            $row->delete();
        } catch (QueryException $e) {
            return response()->json(['ok' => false, 'message' => self::NOT_INSTALLED], 503);
        }

        return response()->json(['ok' => true]);
    }
}
