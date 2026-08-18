<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

if (!function_exists('normalizeRoleName')) {
    function normalizeRoleName($role)
    {
        if (empty($role)) {
            return $role;
        }

        $role = trim($role);

        if (Role::where('name', $role)->exists()) {
            return $role;
        }

        $slug = Str::slug($role);
        $matchedRole = Role::all()->first(function ($item) use ($slug) {
            return Str::slug($item->name) === $slug;
        });

        return $matchedRole ? $matchedRole->name : $role;
    }
}

if (!function_exists('redirectToRoleDashboard')) {
    function redirectToRoleDashboard($user)
    {
        $role = Str::slug($user->getRoleNames()->first()); // e.g., "super-admin"
        return match (true) {
            $role  => redirect()->route('role.dashboard', ['role' => $role]),
            default => redirect()->route('login')->withErrors('Unauthorized role.')
        };
    }
}
if (!function_exists('redirectToLogin')) {
    function redirectToLogin()
    {
        return Redirect::route('login');
    }
}
if (!function_exists('redirectToLoginWithError')) {
    function redirectToLoginWithError($error)
    {
        return Redirect::route('login')->withErrors($error);
    }
}
if (!function_exists('redirectToDashboard')) {
    function redirectToDashboard($user)
    {
        if (!$user) {
            return redirectToLogin();
        }

        return redirectToRoleDashboard($user);
    }
}

if (!function_exists('site_setting')) {
    function site_setting(string $key, mixed $default = null): mixed
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return $default;
            }
        } catch (\Throwable $e) {
            return $default;
        }

        $settings = Cache::rememberForever('site_settings_map', function () {
            return SiteSetting::query()->pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }
}

if (!function_exists('site_setting_url')) {
    function site_setting_url(string $key, mixed $default = null): mixed
    {
        $value = site_setting($key, $default);

        if (empty($value)) {
            return $default;
        }

        return Str::startsWith($value, ['http://', 'https://']) ? $value : asset($value);
    }
}

if (!function_exists('company_icon')) {
    /**
     * The glyph a sister concern wears in the sidebar — a plane for travels, a
     * tree for the interiors house, a microchip for IT, and so on.
     *
     * The `icon` column holds an uploaded logo image, which reads as a smudge at
     * rail size, so the rail draws a glyph instead. Which glyph is derived from
     * the company NAME rather than stored, so a concern added from the admin
     * screen gets a sensible icon with no extra field to fill in. A company that
     * really does want to pin its own icon can put a Bootstrap Icons class
     * (e.g. "bi bi-water") in `icon` and that wins outright.
     *
     * These are BOOTSTRAP ICONS, not the Font Awesome set the rest of the app
     * uses, and deliberately so: the glyphs and accents below are lifted
     * verbatim from the Modular ERP reference build (platform/core/config.js) so
     * the rail is identical to it rather than a lookalike. The six concerns that
     * build ships — group, travels, woodart, IT, shop, construction — keep its
     * exact icon and accent; the rest are chosen to match its visual language.
     *
     * The keyword table is ORDERED and first-match-wins, which is the whole
     * design: "Epal Travels & Consultancy" must read as travel and not as
     * consultancy, and "Epal Travels Group" as travel and not as the holding
     * company — so the specific trades sit above the generic ones.
     *
     * @param  \App\Models\Company|object  $company
     * @return array{class:string, accent:string, tint:string}
     */
    function company_icon($company): array
    {
        $stored = trim((string) ($company->icon ?? ''));

        // Padded on both ends so short keywords can be matched as whole words
        // (" it " must not fire on "digital", " inn " not on "winner").
        $name = ' ' . Str::lower(trim(($company->name ?? '') . ' ' . ($company->short_name ?? ''))) . ' ';

        $map = [
            // ── the six the reference build defines, icon + accent verbatim ──
            [['travel', 'tour', 'airlin', 'aviation', 'air ticket', 'holiday'],            'bi-airplane-fill',        '#2f6bff'],
            [['wood', 'interior', 'furnitur', 'carpent', 'decor'],                         'bi-tree-fill',            '#6f9c1c'],
            [[' it ', 'it solution', 'infotech', 'software', 'technolog', 'digital', 'cyber', 'computer'], 'bi-cpu-fill', '#7b5cff'],
            [['shop', 'store', 'retail', 'ecommerce', 'e-commerce', 'online'],             'bi-shop',                 '#e0356e'],
            [['construction', 'builder', 'infrastructure'],                                'bi-buildings-fill',       '#e2721b'],
            // ── trades the reference has no company for yet ──
            [['propert', 'real estate', 'estate', 'housing', 'developer'],                 'bi-house-door-fill',      '#0d9488'],
            [['manufactur', 'factory', 'industr', 'production', 'mills'],                  'bi-gear-wide-connected',  '#475569'],
            [['textile', 'garment', 'apparel', 'fashion', 'fabric', 'knit'],               'bi-scissors',             '#be123c'],
            [['shipping', 'marine', 'maritime', 'port '],                                  'bi-life-preserver',       '#0e7490'],
            [['logistic', 'courier', 'cargo', 'transport', 'freight', 'delivery'],         'bi-truck',                '#0891b2'],
            [['agro', 'agri', 'farm', 'poultry', 'fisher', 'dairy', 'seed'],               'bi-flower1',              '#16a34a'],
            [['restaurant', 'catering', 'bakery', 'kitchen', 'cafe', ' food'],             'bi-cup-hot-fill',         '#ea580c'],
            [['hotel', 'resort', 'hospitality', ' inn '],                                   'bi-luggage-fill',         '#b45309'],
            [['pharma', 'health', 'medic', 'hospital', 'clinic', 'diagnost'],              'bi-hospital-fill',        '#dc2626'],
            [['academy', 'school', 'educat', 'institute', 'college', 'training'],          'bi-mortarboard-fill',     '#7c3aed'],
            [['media', 'advertis', 'marketing', 'creative', 'studio', 'agency'],           'bi-megaphone-fill',       '#db2777'],
            [['printing', 'press', 'publicat', 'publish'],                                 'bi-printer-fill',         '#57534e'],
            [['energy', 'power', 'solar', 'electric', 'renewable', 'petro'],               'bi-lightning-charge-fill','#f59e0b'],
            [['securities', 'finance', 'capital', 'bank', 'invest', 'insur', 'leasing'],   'bi-bank',                 '#0f766e'],
            [['telecom', 'network', 'communicat', 'broadband', 'fiber'],                   'bi-broadcast-pin',        '#0284c7'],
            [['security', 'guard', 'surveillance', 'protection'],                          'bi-shield-fill-check',    '#334155'],
            [['auto', 'motor', 'vehicle', 'rent a car'],                                    'bi-car-front-fill',       '#1d4ed8'],
            [['engineer', 'fabricat', 'steel', 'machin'],                                   'bi-tools',                '#1e293b'],
            [['chemical', 'plastic', 'polymer', 'paint'],                                   'bi-droplet-fill',         '#7e22ce'],
            [['consult', 'advisory', 'legal', 'associates'],                                'bi-briefcase-fill',       '#6366f1'],
            // Generic last, so "Epal Travels Group" still reads as travel.
            [['group', 'holding', 'conglomerate', 'corporate', 'ventures'],                 'bi-hexagon-fill',         '#1a43bf'],
        ];

        $icon   = 'bi-briefcase-fill';
        $accent = '#64748b';

        foreach ($map as [$keywords, $glyph, $color]) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    $icon   = $glyph;
                    $accent = $color;
                    break 2;
                }
            }
        }

        // An explicitly configured Bootstrap Icons class overrides the guess; an
        // uploaded image path in the same column is simply not an icon and is
        // left to whatever still renders the logo.
        if (Str::startsWith($stored, ['bi-', 'bi '])) {
            $icon = $stored;
        }

        $icon = Str::startsWith($icon, 'bi ') ? $icon : 'bi ' . $icon;

        [$r, $g, $b] = sscanf(ltrim($accent, '#'), '%2x%2x%2x');

        return [
            'class'  => $icon,
            'accent' => $accent,
            // 16% — the reference's `color-mix(in srgb, var(--accent) 16%, transparent)`.
            'tint'   => "rgba({$r}, {$g}, {$b}, 0.16)",
        ];
    }
}

function amountToWords($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety');
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred: $words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Taka = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paisa' : '';
    return ($Taka ? $Taka . 'Taka Only' : '');
}

if (!function_exists('sendBrevoMail')) {

    function sendBrevoMail($toEmail, $toName, $subject, $htmlContent)
    {
        return Http::withHeaders([
            'api-key'      => config('services.brevo.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'to' => [
                [
                    'email' => $toEmail,
                    'name'  => $toName,
                ],
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'sender' => [
                'email' => 'no-reply@epaltravels.com',
                'name'  => 'Epal Group',
            ],
        ]);
    }

}

if (!function_exists('sendSms')) {

    function sendSms($phone, $message)
    {
        $url = "https://msg.mram.com.bd/smsapi";
        $phone = preg_replace('/^0/', '880', $phone);
        $data = [
            "api_key" => config('services.mram.api_key'),
            "type" => "text",
            "contacts" => $phone,
            "senderid" => config('services.mram.sender_id'),
            "msg" => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

}

if (!function_exists('companyBadgeColor')) {
    /**
     * A stable colour scheme per company, so the same company always reads as
     * the same colour wherever it's listed (salary sheet, summaries, etc.).
     *
     * Keyed off the company id rather than its position in a list, so removing
     * or reordering a company doesn't repaint every other one. Returned as raw
     * hex for inline styles — Tailwind can't see class names built at runtime.
     */
    function companyBadgeColor($companyId): array
    {
        $palette = [
            ['bg' => '#ecfeff', 'text' => '#0e7490', 'border' => '#a5f3fc', 'dot' => '#06b6d4'], // cyan
            ['bg' => '#f5f3ff', 'text' => '#6d28d9', 'border' => '#ddd6fe', 'dot' => '#8b5cf6'], // violet
            ['bg' => '#fffbeb', 'text' => '#b45309', 'border' => '#fde68a', 'dot' => '#f59e0b'], // amber
            ['bg' => '#ecfdf5', 'text' => '#047857', 'border' => '#a7f3d0', 'dot' => '#10b981'], // emerald
            ['bg' => '#fff1f2', 'text' => '#be123c', 'border' => '#fecdd3', 'dot' => '#f43f5e'], // rose
            ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#bfdbfe', 'dot' => '#3b82f6'], // blue
            ['bg' => '#fdf4ff', 'text' => '#a21caf', 'border' => '#f5d0fe', 'dot' => '#d946ef'], // fuchsia
            ['bg' => '#f7fee7', 'text' => '#4d7c0f', 'border' => '#d9f99d', 'dot' => '#84cc16'], // lime
            ['bg' => '#fff7ed', 'text' => '#c2410c', 'border' => '#fed7aa', 'dot' => '#f97316'], // orange
            ['bg' => '#f0fdfa', 'text' => '#0f766e', 'border' => '#99f6e4', 'dot' => '#14b8a6'], // teal
        ];

        // No company on file — a neutral grey, never a palette colour that
        // would make "unassigned" look like a real company.
        if (empty($companyId)) {
            return ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb', 'dot' => '#9ca3af'];
        }

        return $palette[((int) $companyId) % count($palette)];
    }
}

if (!function_exists('payslipRound')) {
    /**
     * Payslip figures are shown as whole taka, always rounded up — 379.63
     * becomes 380 and 15199.33 becomes 15200.
     *
     * Totals on a payslip must be recomputed from these rounded lines rather
     * than rounded on their own, or the column stops adding up in front of the
     * employee: 1000 + 379.63 + 3.70 stores as 1383.33, which alone would print
     * as 1384 while its rounded parts read 1000 + 380 + 4 = 1384. Rounding each
     * part first and summing keeps the two in step.
     */
    function payslipRound($value): int
    {
        return (int) ceil((float) ($value ?? 0));
    }
}

if (!function_exists('payslipAmount')) {
    /**
     * Display form of payslipRound() — whole taka with thousands separators.
     */
    function payslipAmount($value): string
    {
        return number_format(payslipRound($value));
    }
}

