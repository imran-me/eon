{{--
    The party ledger email — sent to an agent / customer / vendor by
    PartyNotifier in one of three framings ($mode):
      activity   new entries were just posted (automatic)
      statement  the statement for a period, on request (Email button)
      reminder   payment reminder for an outstanding due (Reminder button)

    Inline styles and tables only: Gmail, Outlook and most Bangla webmail
    clients strip <style> blocks. Bangla and English side by side, figures in
    English, the way the payslip notice does it.

    Expects: $party, $type, $typeLabel, $side, $code, $company, $mode,
             $entries, $closing, $labels, $date_from, $date_to
--}}
@php
    $taka = fn ($v) => '৳' . number_format((float) $v, 2);
    $coName = $company?->name ?: config('app.name');

    // Debit raises the balance on every party ledger; what that *means*
    // depends on which way the money runs (PartyStatementController::TYPES).
    $receivable = $side === 'receivable';
    $drWord = $receivable ? 'Charged / বিল'     : 'Purchased from you / ক্রয়';
    $crWord = $receivable ? 'Paid / পরিশোধ'      : 'Paid to you / প্রদান';

    if (abs($closing) < 0.005) {
        $balEn = 'Settled'; $balBn = 'পরিশোধিত'; $balColor = '#059669';
    } elseif ($receivable) {
        $balEn = $closing > 0 ? 'Outstanding due' : 'Advance with us';
        $balBn = $closing > 0 ? 'বকেয়া'          : 'অগ্রিম জমা';
        $balColor = $closing > 0 ? '#dc2626' : '#059669';
    } else {
        $balEn = $closing > 0 ? 'Payable to you' : 'Advance paid to you';
        $balBn = $closing > 0 ? 'আপনার পাওনা'    : 'অগ্রিম প্রদান';
        $balColor = $closing > 0 ? '#1d4ed8' : '#059669';
    }

    $totalDr = (float) $entries->sum('debit');
    $totalCr = (float) $entries->sum('credit');
    $n = $entries->count();
    $mode = $mode ?? 'activity';

    // A period statement can run to hundreds of rows; the email shows the
    // latest few and the PDF carries the rest.
    $cap = 25;
    $shown = ($mode === 'statement' && $n > $cap) ? $entries->slice(-$cap)->values() : $entries;
    $hidden = $n - $shown->count();

    $fmtD = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : null;
    $period = ($date_from ?? null) || ($date_to ?? null)
        ? ($fmtD($date_from ?? null) ?: 'start') . ' – ' . ($fmtD($date_to ?? null) ?: now()->format('d M Y'))
        : null;
@endphp
<div style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;">
    <tr>
        <td style="background:#1e3a8a;padding:20px 28px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#ffffff;font-size:18px;font-weight:700;">{{ $coName }}</td>
                    <td align="right" style="color:#bfdbfe;font-size:12px;">{{ now()->format('d M Y, h:i A') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 28px 8px;">
            <p style="margin:0 0 4px;font-size:15px;">প্রিয় <strong>{{ $party->name }}</strong>,</p>
            <p style="margin:0 0 12px;font-size:13px;color:#475569;">Dear {{ $party->name }},</p>

            @if($mode === 'statement')
                <p style="margin:0 0 6px;font-size:14px;line-height:1.7;">
                    {{ $coName }}-এ আপনার {{ $typeLabel }} লেজারের স্টেটমেন্ট
                    ({{ $period ?: 'শুরু থেকে আজ পর্যন্ত' }}) নিচে দেওয়া হলো
                    এবং সম্পূর্ণ স্টেটমেন্ট (PDF) সংযুক্ত আছে।
                </p>
                <p style="margin:0 0 20px;font-size:13px;line-height:1.7;color:#475569;">
                    Here is your {{ strtolower($typeLabel) }} ledger statement with {{ $coName }}
                    {{ $period ? "for $period" : 'from the beginning to date' }}.
                    The entries are listed below and the full statement is attached as a PDF.
                </p>
            @elseif($mode === 'reminder')
                <p style="margin:0 0 6px;font-size:14px;line-height:1.7;">
                    {{ $coName }}-এ আপনার লেজারে <strong>{{ $taka($closing) }}</strong> বকেয়া রয়েছে।
                    অনুগ্রহ করে যত দ্রুত সম্ভব পরিশোধের ব্যবস্থা করুন। আপনার সম্পূর্ণ লেজার স্টেটমেন্ট (PDF) সংযুক্ত আছে।
                </p>
                <p style="margin:0 0 20px;font-size:13px;line-height:1.7;color:#475569;">
                    This is a friendly reminder that <strong>{{ $taka($closing) }}</strong> is outstanding on your
                    ledger with {{ $coName }}. Please arrange payment at your earliest convenience.
                    Your full ledger statement is attached as a PDF.
                </p>
            @else
                <p style="margin:0 0 6px;font-size:14px;line-height:1.7;">
                    {{ $coName }}-এ আপনার {{ $typeLabel }} লেজারে
                    {{ $n === 1 ? 'একটি নতুন এন্ট্রি' : $n . 'টি নতুন এন্ট্রি' }} যুক্ত হয়েছে।
                    বিস্তারিত নিচে দেওয়া হলো এবং আপনার সম্পূর্ণ লেজার স্টেটমেন্ট (PDF) সংযুক্ত আছে।
                </p>
                <p style="margin:0 0 20px;font-size:13px;line-height:1.7;color:#475569;">
                    {{ $n === 1 ? 'A new entry has' : $n . ' new entries have' }} been posted on your
                    {{ strtolower($typeLabel) }} ledger with {{ $coName }}. The details are below and your
                    full ledger statement is attached as a PDF.
                </p>
                @if(! empty($invoiceAttached))
                    <p style="margin:-12px 0 20px;font-size:13px;line-height:1.7;color:#475569;">
                        নতুন বিক্রয়ের ইনভয়েস (PDF) সংযুক্ত আছে। / The sales invoice for these entries is attached as well.
                    </p>
                @endif
            @endif
        </td>
    </tr>

    @if($n > 0)
    <tr>
        <td style="padding:0 28px;">
            @if($hidden > 0)
                <p style="margin:0 0 6px;font-size:11px;color:#64748b;">Latest {{ $shown->count() }} of {{ $n }} entries — the attached PDF has all of them. / সর্বশেষ {{ $shown->count() }}টি এন্ট্রি; সবগুলো সংযুক্ত PDF-এ আছে।</p>
            @endif
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-collapse:collapse;font-size:12px;">
                <tr style="background:#f8fafc;">
                    <th align="left"  style="padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;">Date / তারিখ</th>
                    <th align="left"  style="padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;">Particulars / বিবরণ</th>
                    <th align="right" style="padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;">{{ $drWord }}</th>
                    <th align="right" style="padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;">{{ $crWord }}</th>
                    <th align="right" style="padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600;">Balance / ব্যালেন্স</th>
                </tr>
                @foreach($shown as $t)
                    @php
                        $label = $labels[$t->type] ?? ucfirst(str_replace('_', ' ', (string) $t->type));
                        $date  = $t->payment_date ? \Carbon\Carbon::parse($t->payment_date)->format('d M Y') : '—';
                    @endphp
                    <tr>
                        <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;white-space:nowrap;">{{ $date }}</td>
                        <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;">
                            <strong>{{ $label }}</strong>
                            @if($t->reference_no)<br><span style="color:#64748b;font-family:Consolas,monospace;font-size:11px;">{{ $t->reference_no }}</span>@endif
                            @if($t->payment_method)<span style="color:#64748b;font-size:11px;"> · {{ ucfirst(str_replace('_', ' ', $t->payment_method)) }}</span>@endif
                            @if($t->remarks)<br><span style="color:#64748b;font-size:11px;">{{ \Illuminate\Support\Str::limit($t->remarks, 120) }}</span>@endif
                        </td>
                        <td align="right" style="padding:8px 10px;border-bottom:1px solid #f1f5f9;white-space:nowrap;">{{ $t->debit  > 0 ? $taka($t->debit)  : '—' }}</td>
                        <td align="right" style="padding:8px 10px;border-bottom:1px solid #f1f5f9;white-space:nowrap;">{{ $t->credit > 0 ? $taka($t->credit) : '—' }}</td>
                        <td align="right" style="padding:8px 10px;border-bottom:1px solid #f1f5f9;white-space:nowrap;font-weight:600;">{{ $taka($t->balance) }}</td>
                    </tr>
                @endforeach
                @if($n > 1)
                    <tr style="background:#f8fafc;">
                        <td colspan="2" style="padding:8px 10px;font-weight:600;">Total / মোট</td>
                        <td align="right" style="padding:8px 10px;font-weight:600;">{{ $totalDr > 0 ? $taka($totalDr) : '—' }}</td>
                        <td align="right" style="padding:8px 10px;font-weight:600;">{{ $totalCr > 0 ? $taka($totalCr) : '—' }}</td>
                        <td></td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
    @endif

    <tr>
        <td style="padding:20px 28px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;background:#f8fafc;">
                <tr>
                    <td style="padding:14px 16px;">
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Current balance / বর্তমান ব্যালেন্স</div>
                        <div style="font-size:22px;font-weight:700;color:{{ $balColor }};margin-top:2px;">{{ $taka(abs($closing)) }}</div>
                        <div style="font-size:12px;color:#475569;margin-top:2px;">{{ $balEn }} · {{ $balBn }}</div>
                    </td>
                    <td align="right" style="padding:14px 16px;vertical-align:top;">
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Party code</div>
                        <div style="font-size:14px;font-weight:700;font-family:Consolas,monospace;margin-top:2px;">{{ $code }}</div>
                        <div style="font-size:12px;color:#475569;margin-top:2px;">{{ $typeLabel }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:0 28px 24px;font-size:12px;line-height:1.7;color:#475569;">
            <p style="margin:0 0 8px;">
                কোনো এন্ট্রি বা ব্যালেন্স নিয়ে প্রশ্ন থাকলে অনুগ্রহ করে এই ইমেইলের উত্তরে অথবা
                @if($company?->phone) {{ $company->phone }} নম্বরে @endif আমাদের সাথে যোগাযোগ করুন।
            </p>
            <p style="margin:0;">
                If anything on this statement looks wrong, reply to this email
                @if($company?->phone) or call {{ $company->phone }} @endif and we will sort it out.
            </p>
        </td>
    </tr>

    <tr>
        <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 28px;font-size:11px;color:#94a3b8;line-height:1.6;">
            {{ $coName }}@if($company?->address) · {{ $company->address }}@endif
            @if($company?->email)<br>{{ $company->email }}@endif
            <br>This is an automated notice from the ledger system. · এটি লেজার সিস্টেম থেকে পাঠানো স্বয়ংক্রিয় বার্তা।
        </td>
    </tr>
</table>
</div>
