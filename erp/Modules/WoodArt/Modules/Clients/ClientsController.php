<?php

namespace Modules\WoodArt\Modules\Clients;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\WoodArt\Modules\Clients\Models\Client;
use Modules\WoodArt\Modules\Projects\Models\Project;

/**
 * Wood Art · Clients — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/clients/.
 *
 * Its own framing: Directory is who Woodart builds for; Portfolio is what each
 * of them is actually worth once projects and estimates are counted; Segments
 * is which kind of client the business runs on.
 */
class ClientsController extends Controller
{
    /** Tab labels — TAB_COPY[key][0] and module.json agree here. */
    public const SECTIONS = [
        'directory' => 'Directory',
        'portfolio' => 'Portfolio',
        'segments'  => 'Segments',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:241-243). */
    private const SUBTITLES = [
        'directory' => 'Homeowners, developers and corporates — who Woodart builds for.',
        'portfolio' => 'What each client is worth: projects, contract value, margin and open quotes.',
        'segments'  => 'Which kind of client the business actually runs on.',
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(string $role, string $section = 'directory'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        $clients  = $this->clients();
        $rollup   = $this->rollup($clients);

        return view('woodart::clients', [
            'waModule' => 'clients',
            'waRoute'  => 'role.woodart.clients',
            'waIcon'   => 'person-hearts',
            'section'  => $section,
            'sections' => self::SECTIONS,
            'title'    => self::SECTIONS[$section],
            'subtitle' => self::SUBTITLES[$section],
            'clients'  => $clients,
            'rollup'   => $rollup,
            'stats'    => $this->stats($rollup),
            'segments' => $this->segments($rollup),
            'types'    => Client::TYPES,
        ]);
    }

    /** The "New Client" form — a page, for the same reason as Projects. */
    public function create(string $role): View
    {
        return view('woodart::client-form', $this->formData(null));
    }

    public function store(Request $request, string $role): RedirectResponse
    {
        $client = new Client($this->validated($request) + [
            'ext_id'     => $this->nextExtId(),
            'created_on' => now()->toDateString(),
        ]);
        $client->save();

        return redirect()
            ->route('role.woodart.clients', ['role' => $role, 'section' => 'directory'])
            ->with('wa_status', "{$client->ext_id} — {$client->name} was added.");
    }

    public function edit(string $role, Client $client): View
    {
        return view('woodart::client-form', $this->formData($client));
    }

    /**
     * `ext_id` is not editable, for the same reason as a project's: other rows
     * reference this record. Renaming `name` IS allowed, but note it does not
     * follow through to projects — they store the client's name as a string,
     * so a rename detaches existing projects from this client until they are
     * updated too. The form says so.
     */
    public function update(Request $request, string $role, Client $client): RedirectResponse
    {
        $before = $client->name;
        $client->fill($this->validated($request))->save();

        $note = $client->name !== $before
            ? " Projects still naming “{$before}” will no longer roll up to this client."
            : '';

        return redirect()
            ->route('role.woodart.clients', ['role' => $role, 'section' => 'directory'])
            ->with('wa_status', "{$client->ext_id} — {$client->name} was updated.{$note}");
    }

    public function confirmDelete(string $role, Client $client): View
    {
        // Show how much work is attached, so the decision is informed.
        $linked = $this->rollup(collect([$client]))->first();

        return view('woodart::client-delete', [
            'waModule' => 'clients',
            'waRoute'  => 'role.woodart.clients',
            'waIcon'   => 'person-hearts',
            'section'  => 'directory',
            'sections' => self::SECTIONS,
            'title'    => 'Remove Client',
            'subtitle' => "Check this is the right client before removing {$client->ext_id}.",
            'client'   => $client,
            'linked'   => $linked,
        ]);
    }

    /**
     * Soft delete — the row is kept with a removal date so the client can be
     * restored and its code is never reused. Projects are NOT touched: they
     * record the client's name, the work really happened, and erasing the
     * customer record must not rewrite the job history.
     */
    public function destroy(string $role, Client $client): RedirectResponse
    {
        $label = "{$client->ext_id} — {$client->name}";
        $client->delete();

        return redirect()
            ->route('role.woodart.clients', ['role' => $role, 'section' => 'directory'])
            ->with('wa_status', "{$label} was removed. Their projects are untouched.");
    }

    /** One rule set for create and update, so the two cannot drift. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:160'],
            'type'    => ['required', Rule::in(Client::TYPES)],
            'contact' => ['nullable', 'string', 'max:160'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'email'   => ['nullable', 'email', 'max:160'],
            'area'    => ['nullable', 'string', 'max:120'],
            'since'   => ['nullable', 'date'],
        ]);
    }

    /** Shared payload for the create and edit forms. */
    private function formData(?Client $client): array
    {
        return [
            'waModule' => 'clients',
            'waRoute'  => 'role.woodart.clients',
            'waIcon'   => 'person-hearts',
            'section'  => 'directory',
            'sections' => self::SECTIONS,
            'title'    => $client ? "Edit {$client->ext_id}" : 'New Client',
            'subtitle' => $client
                ? 'Change what has moved on. The client code cannot change.'
                : 'Add a homeowner, developer, corporate or retail buyer. Only the name is required.',
            'types'    => Client::TYPES,
            'client'   => $client,
            'nextExt'  => $client?->ext_id ?? $this->nextExtId(),
        ];
    }

    /** The next WAC-nnn code, reading the highest existing number. */
    private function nextExtId(): string
    {
        try {
            if (! Schema::hasTable('wa_clients')) {
                return 'WAC-101';
            }

            $max = Client::withTrashed()
                ->selectRaw("MAX(CAST(SUBSTRING(ext_id, 5) AS UNSIGNED)) AS n")
                ->where('ext_id', 'like', 'WAC-%')
                ->value('n');

            return 'WAC-' . max(101, ((int) $max) + 1);
        } catch (\Throwable $e) {
            report($e);

            return 'WAC-101';
        }
    }

    /**
     * The register, newest client first.
     *
     * Fault-tolerant for the same reason as Projects: a server whose database
     * has not had this module's migration run has no `wa_clients` table, and
     * querying it would throw. It degrades to the empty state instead.
     */
    private function clients(): Collection
    {
        try {
            if (! Schema::hasTable('wa_clients')) {
                return collect();
            }

            return Client::query()->orderBy('name')->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Each client with its project figures attached.
     *
     * ⚠ THE JOIN IS BY NAME, NOT BY ID. `wa_projects.client` stores the client's
     * NAME string — the reference's own design (see the clients migration note).
     * So a project only rolls up to a client when the two strings match
     * exactly. Projects naming a client that is not in the register are counted
     * separately as "unregistered" rather than silently dropped, because a
     * missing roll-up is exactly how a portfolio total quietly goes wrong.
     */
    private function rollup(Collection $clients): Collection
    {
        try {
            $projects = Schema::hasTable('wa_projects') ? Project::query()->get() : collect();
        } catch (\Throwable $e) {
            report($e);
            $projects = collect();
        }

        $byName = $projects->groupBy(fn (Project $p) => mb_strtolower(trim((string) $p->client)));

        return $clients->map(function (Client $c) use ($byName) {
            $own = $byName->get(mb_strtolower(trim($c->name)), collect());

            return [
                'client'   => $c,
                'projects' => $own->count(),
                'live'     => $own->filter->is_live->count(),
                'value'    => (int) $own->sum('value'),
                'cost'     => (int) $own->sum('cost'),
                'margin'   => (int) $own->sum('value') - (int) $own->sum('cost'),
            ];
        });
    }

    /** The directory KPIs. */
    private function stats(Collection $rollup): array
    {
        $withWork = $rollup->filter(fn (array $r) => $r['projects'] > 0);

        return [
            'clients'  => $rollup->count(),
            'live'     => $rollup->filter(fn (array $r) => $r['live'] > 0)->count(),
            'value'    => (int) $rollup->sum('value'),
            'repeat'   => $rollup->filter(fn (array $r) => $r['projects'] > 1)->count(),
            'idle'     => $rollup->count() - $withWork->count(),
            'segments' => $rollup->pluck('client.type')->unique()->count(),
            'top'      => $rollup->sortByDesc('value')->first()['client']->name ?? null,
            'avg'      => $rollup->count() ? (int) round($rollup->sum('value') / $rollup->count()) : 0,
        ];
    }

    /** Segment roll-up — clients, projects and value per type. */
    private function segments(Collection $rollup): array
    {
        $total = max(1, (int) $rollup->sum('value'));

        return collect(Client::TYPES)
            ->map(function (string $t) use ($rollup, $total) {
                $in = $rollup->filter(fn (array $r) => $r['client']->type === $t);

                return [
                    'label'    => $t,
                    'clients'  => $in->count(),
                    'projects' => (int) $in->sum('projects'),
                    'value'    => $v = (int) $in->sum('value'),
                    'margin'   => (int) $in->sum('margin'),
                    'share'    => (int) round($v / $total * 100),
                ];
            })
            ->filter(fn (array $r) => $r['clients'] > 0)
            ->values()
            ->all();
    }
}
