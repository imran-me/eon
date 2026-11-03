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

        // ACCENTS: deep, and spaced around the hue wheel. Re-set 2026-08-19 on
        // the owner's instruction — "still similar, use rgb color / deep color".
        //
        // The accents that shipped here were transcribed from the Modular ERP
        // reference build, and three of the concerns actually trading landed
        // within about forty degrees of one another: GROUP #1a43bf, TRAVELS
        // #2f6bff and IT #7b5cff are all blue-to-violet. Side by side as a 5px
        // rail on a bank card, or as a 40px tile in the company switcher, they
        // are indistinguishable — the owner's words were "I see all color same".
        // Saturation could not fix that; only hue can.
        //
        // Two rules now govern this column, and a new company must respect both:
        //   1. DEEP. Lightness roughly 25–40%, so the colour holds at 5px on
        //      white and still carries a white glyph on the sidebar tile.
        //   2. SPACED. At least ~45° of hue from every other trade a company on
        //      this installation might match. Check the neighbours before
        //      picking; two companies that both read "blue" is the bug.
        //
        // GROUP is deliberately the one with no hue at all. It is the umbrella,
        // not a trade — giving it graphite says that, and it is what breaks the
        // blue pile-up, since a holding company and a travel agency both want to
        // be corporate blue and only one of them can have it.
        //
        // This column is read by the sidebar's company switcher (--rail-accent
        // in layout/sidebar.blade.php) AND by the bank cards' company rail
        // (BankControllerV2::companyRail). That is the point: one source of
        // truth, so a concern is the same colour wherever it appears. Editing a
        // value here moves both, together, which is the only way they can stay
        // 100% matched.
        $map = [
            // ── the six the reference build defines ──                          hue
            [['travel', 'tour', 'airlin', 'aviation', 'air ticket', 'holiday'],            'bi-airplane-fill',        '#1e40af'], // 224 deep royal blue
            [['wood', 'interior', 'furnitur', 'carpent', 'decor'],                         'bi-tree-fill',            '#4d7c0f'], //  85 deep apple green
            [[' it ', 'it solution', 'infotech', 'software', 'technolog', 'digital', 'cyber', 'computer'], 'bi-cpu-fill', '#6b21a8'], // 277 deep purple
            [['shop', 'store', 'retail', 'ecommerce', 'e-commerce', 'online'],             'bi-shop',                 '#9f1239'], // 345 deep rose
            [['construction', 'builder', 'infrastructure'],                                'bi-buildings-fill',       '#9a3412'], //  22 deep burnt orange
            // ── trades the reference has no company for yet ──
            [['propert', 'real estate', 'estate', 'housing', 'developer'],                 'bi-house-door-fill',      '#115e59'], // 174 deep teal
            [['manufactur', 'factory', 'industr', 'production', 'mills'],                  'bi-gear-wide-connected',  '#854d0e'], //  38 deep bronze
            [['textile', 'garment', 'apparel', 'fashion', 'fabric', 'knit'],               'bi-scissors',             '#831843'], // 331 deep wine
            [['shipping', 'marine', 'maritime', 'port '],                                  'bi-life-preserver',       '#155e75'], // 197 deep marine
            [['logistic', 'courier', 'cargo', 'transport', 'freight', 'delivery'],         'bi-truck',                '#0e7490'], // 192 deep cyan
            [['agro', 'agri', 'farm', 'poultry', 'fisher', 'dairy', 'seed'],               'bi-flower1',              '#15803d'], // 142 deep leaf green
            [['restaurant', 'catering', 'bakery', 'kitchen', 'cafe', ' food'],             'bi-cup-hot-fill',         '#b45309'], //  33 deep amber
            [['hotel', 'resort', 'hospitality', ' inn '],                                   'bi-luggage-fill',         '#7c2d12'], //  17 deep rust
            [['pharma', 'health', 'medic', 'hospital', 'clinic', 'diagnost'],              'bi-hospital-fill',        '#991b1b'], //   0 deep red
            [['academy', 'school', 'educat', 'institute', 'college', 'training'],          'bi-mortarboard-fill',     '#5b21b6'], // 263 deep indigo-violet
            [['media', 'advertis', 'marketing', 'creative', 'studio', 'agency'],           'bi-megaphone-fill',       '#9d174d'], // 337 deep magenta
            [['printing', 'press', 'publicat', 'publish'],                                 'bi-printer-fill',         '#44403c'], //   – deep stone
            [['energy', 'power', 'solar', 'electric', 'renewable', 'petro'],               'bi-lightning-charge-fill','#a16207'], //  41 deep gold
            [['securities', 'finance', 'capital', 'bank', 'invest', 'insur', 'leasing'],   'bi-bank',                 '#134e4a'], // 176 deep pine
            [['telecom', 'network', 'communicat', 'broadband', 'fiber'],                   'bi-broadcast-pin',        '#075985'], // 201 deep ocean
            [['security', 'guard', 'surveillance', 'protection'],                          'bi-shield-fill-check',    '#3f3f46'], //   – deep zinc
            [['auto', 'motor', 'vehicle', 'rent a car'],                                    'bi-car-front-fill',       '#1e3a8a'], // 226 deep navy
            [['engineer', 'fabricat', 'steel', 'machin'],                                   'bi-tools',                '#292524'], //   – deep charcoal
            [['chemical', 'plastic', 'polymer', 'paint'],                                   'bi-droplet-fill',         '#581c87'], // 280 deep grape
            [['consult', 'advisory', 'legal', 'associates'],                                'bi-briefcase-fill',       '#3730a3'], // 244 deep indigo
            // Generic last, so "Epal Travels Group" still reads as travel.
            // Graphite on purpose — see the note above.
            [['group', 'holding', 'conglomerate', 'corporate', 'ventures'],                 'bi-hexagon-fill',         '#1e293b'], //   – graphite
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

    /**
     * @param  array  $attachments  Optional files to attach, each
     *                              ['name' => 'Payslip.pdf', 'content' => <raw bytes>].
     *                              Added as an optional fifth argument so the nine
     *                              existing callers keep working untouched; when it is
     *                              empty the request body is byte-identical to what it
     *                              has always been.
     */
    function sendBrevoMail($toEmail, $toName, $subject, $htmlContent, array $attachments = [])
    {
        $payload = [
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
        ];

        // Brevo takes attachments as base64 under `attachment`. Anything without
        // both a name and content is dropped rather than sent half-formed — an
        // email that arrives with a broken attachment is worse than one without.
        $encoded = [];
        foreach ($attachments as $file) {
            if (empty($file['name']) || empty($file['content'])) {
                continue;
            }
            $encoded[] = [
                'name'    => $file['name'],
                'content' => base64_encode($file['content']),
            ];
        }

        if ($encoded) {
            $payload['attachment'] = $encoded;
        }

        return Http::withHeaders([
            'api-key'      => config('services.brevo.key'),
            'Content-Type' => 'application/json',
        ])->post(rtrim(config('services.brevo.endpoint', 'https://api.brevo.com'), '/') . '/v3/smtp/email', $payload);
    }

}

if (!function_exists('sendSms')) {

    function sendSms($phone, $message)
    {
        app()->forgetInstance('sms.last_error');

        $url = config('services.mram.endpoint', 'https://msg.mram.com.bd/smsapi');
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($phone, '01')) {
            $phone = '88' . $phone;
        }

        if (!preg_match('/^8801[3-9][0-9]{8}$/', $phone)) {
            app()->instance('sms.last_error', 'Invalid mobile number. Use +8801XXXXXXXXX.');
            return false;
        }

        if (!filled(config('services.mram.api_key'))) {
            app()->instance('sms.last_error', 'SMS API key is not configured.');
            return false;
        }
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            app()->instance('sms.last_error', $curlError !== '' ? $curlError : 'SMS gateway request failed.');
            return false;
        }

        if (trim((string) $result) === '') {
            app()->instance('sms.last_error', 'SMS gateway returned an empty response.');
            return false;
        }

        $body = trim((string) $result);
        $gatewayErrors = [
            '1002' => 'Sender ID not found.',
            '1003' => 'SMS API not found.',
            '1004' => 'Message rejected as spam.',
            '1005' => 'SMS gateway internal error.',
            '1006' => 'SMS gateway internal error.',
            '1007' => 'Insufficient SMS balance.',
            '1008' => 'SMS message is empty.',
            '1009' => 'SMS message type is not set.',
            '1010' => 'Invalid SMS gateway credentials.',
            '1011' => 'Invalid SMS gateway user.',
        ];
        if (isset($gatewayErrors[$body])) {
            app()->instance('sms.last_error', $gatewayErrors[$body] . ' (code ' . $body . ')');
            return false;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $status = $decoded['status_code'] ?? $decoded['response_code'] ?? $decoded['status'] ?? null;
            if ($status !== null && !in_array(strtolower((string) $status), ['200', '201', '202', '1000', 'ok', 'success'], true)) {
                app()->instance('sms.last_error', 'SMS gateway rejected the request: ' . mb_substr($body, 0, 500));
                return false;
            }
            if ($status === null && (isset($decoded['error']) || isset($decoded['error_message']))) {
                app()->instance('sms.last_error', 'SMS gateway rejected the request: ' . mb_substr($body, 0, 500));
                return false;
            }
        }

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


if (!function_exists('user_shortcuts')) {
    /**
     * The signed-in user's pinned pages of one kind ('tab' or 'menu'), in the
     * order they were pinned. Read by the shared shell on EVERY page
     * (layout/shortcuts.blade.php and the sidebar), so two things matter:
     *
     *  - it is memoised, so the strip and the sidebar cost one query between
     *    them, not one each;
     *  - it cannot throw. Until the user_shortcuts migration has run on a
     *    server the query fails, and a failure here would 500 every page for
     *    every company. It degrades to "no shortcuts" instead.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\UserShortcut>
     */
    function user_shortcuts(string $kind): \Illuminate\Support\Collection
    {
        static $byKind = [];

        if (array_key_exists($kind, $byKind)) {
            return $byKind[$kind];
        }

        try {
            $userId = auth()->id();
            $byKind[$kind] = $userId
                ? \App\Models\UserShortcut::forUser((int) $userId)
                    ->ofKind($kind)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                : collect();
        } catch (\Throwable $e) {
            $byKind[$kind] = collect();
        }

        return $byKind[$kind];
    }
}
