<?php

namespace Modules\WoodArt\Modules\Accounts\Support;

use App\Models\Account;
use Illuminate\Support\Facades\Auth;

/**
 * Which company's projects a "Project Accounts" screen is showing.
 *
 * The project engine (cost sheet, client due sheet, supplier dues, posting to the
 * books, bulk entry) used to be Wood Art only, with company 6 written into it.
 * It is company-neutral now: every query asks THIS class which company it is
 * working for. Owner (2026-10-06): Munshi Villa is a CONSTRUCTION project, and
 * each company's books stay its own — Interiors never carries construction
 * money — while the screens are shared so no company waits for its own dashboard.
 *
 * ─── HOW THE COMPANY IS CHOSEN ───────────────────────────────────────────────
 * From the URL, never from user input:
 *     /{role}/woodart/accounts/...       → company 6  (Wood Art Interiors)
 *     /{role}/construction/accounts/...  → company 3  (Epal Constructions)
 * A console command names it explicitly with force(). Anything unrecognised
 * falls back to Wood Art, which is exactly what the code did before this class
 * existed — so a Wood Art page behaves as it always has.
 *
 * ─── WHO MAY LOOK ────────────────────────────────────────────────────────────
 * assertCanView(): the people who may see that company's expenses (view all
 * expense), or who belong to that company. A Travels employee who guesses the
 * URL is refused; the Wood Art pages used to be open to any signed-in user.
 */
final class ProjectContext
{
    /** URL segment after the role  →  company id */
    private const SEGMENTS = ['woodart' => 6, 'construction' => 3];

    /**
     * Per company. Account CODES, not ids: a code resolves to the company's own
     * account when it has one, else the shared one — Interiors owns 1315/4410,
     * Construction uses the shared 1314/4310.
     */
    private const COMPANIES = [
        6 => [
            'key'        => 'woodart',
            'name'       => 'Wood Art Interiors',
            'receivable' => '1315',   // Receivable - Interiors
            'revenue'    => '4410',   // Contract Revenue - Interiors
            'category'   => 91,       // Interior Project Cost
        ],
        3 => [
            'key'        => 'construction',
            'name'       => 'Epal Constructions',
            'receivable' => '1314',   // Receivable - Construction
            'revenue'    => '4310',   // Contract Revenue - Construction
            'category'   => 92,       // Construction Project Cost
        ],
    ];

    private static ?int $forced = null;

    /** For console commands and tests: name the company explicitly (null to stop forcing). */
    public static function force(?int $companyId): void
    {
        if ($companyId !== null && ! isset(self::COMPANIES[$companyId])) {
            throw new \InvalidArgumentException("Company {$companyId} has no Project Accounts set up.");
        }
        self::$forced = $companyId;
    }

    public static function id(): int
    {
        if (self::$forced !== null) {
            return self::$forced;
        }

        $segment = request()->segment(2);

        return self::SEGMENTS[$segment] ?? 6;
    }

    public static function supported(int $companyId): bool
    {
        return isset(self::COMPANIES[$companyId]);
    }

    private static function cfg(): array
    {
        return self::COMPANIES[self::id()];
    }

    public static function key(): string
    {
        return self::cfg()['key'];
    }

    public static function name(): string
    {
        return self::cfg()['name'];
    }

    /** The company's "<Something> Project Cost" expense category. */
    public static function categoryId(): int
    {
        return self::cfg()['category'];
    }

    /** Route name for one of this module's screens: '' = the tab pages, 'bulk', 'project', 'project.bill' … */
    public static function routeName(string $suffix = ''): string
    {
        return 'role.' . self::key() . '.accounts' . ($suffix !== '' ? '.' . $suffix : '');
    }

    /** route() for one of this module's screens, filling in {role} from the current URL. */
    public static function route(string $suffix = '', array $params = []): string
    {
        return route(self::routeName($suffix), $params + ['role' => request()->route('role')]);
    }

    /**
     * 'receivable' | 'revenue' → the account to post to. The company's own when it
     * has one with that code, otherwise the shared account.
     */
    public static function account(string $which): Account
    {
        $code = self::cfg()[$which];
        $co   = self::id();

        $a = Account::where('code', $code)->where('status', 1)
            ->where(fn ($q) => $q->where('company_id', $co)->orWhereNull('company_id'))
            ->orderByRaw('company_id IS NULL')   // the company's own first
            ->first();

        if (! $a) {
            throw new \RuntimeException("Account {$code} is missing from the chart of accounts, so this cannot be posted.");
        }

        return $a;
    }

    /** Whoever may see this company's expenses, or belongs to it. */
    public static function canView(): bool
    {
        $u = Auth::user();

        return $u
            && ($u->can('view all expense') || (int) $u->company_id === self::id());
    }

    public static function assertCanView(): void
    {
        abort_unless(self::canView(), 403, 'These project accounts belong to another company.');
    }
}
