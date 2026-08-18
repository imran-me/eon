<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadVisaDocument;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CrmDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // ── Today's Lead Summary ─────────────────────────────────────────
        $summary = [
            'total'      => Lead::whereNull('deleted_at')->count(),
            'new'        => Lead::whereNull('deleted_at')->where('status', 'new')->count(),
            'quoted'     => Lead::whereNull('deleted_at')->where('status', 'proposal_sent')->count(),
            'won'        => Lead::whereNull('deleted_at')->where('status', 'won')->count(),
            'lost'       => Lead::whereNull('deleted_at')->where('status', 'lost')->count(),
            'today_new'  => Lead::whereNull('deleted_at')->whereDate('created_at', $today)->count(),
        ];

        $total    = $summary['total'];
        $won      = $summary['won'];
        $lost     = $summary['lost'];
        $closed   = $won + $lost;
        $summary['conversion_rate'] = $closed > 0 ? round(($won / $closed) * 100, 1) : 0;

        // ── By Type ──────────────────────────────────────────────────────
        $byType = Lead::whereNull('deleted_at')
            ->select('lead_type', DB::raw('count(*) as total'))
            ->groupBy('lead_type')
            ->pluck('total', 'lead_type')
            ->toArray();

        // ── Follow-Up Alerts ─────────────────────────────────────────────
        $tomorrow = Carbon::tomorrow();

        $overdueFollowups = LeadFollowup::with('lead:id,name,phone,lead_type')
            ->where('status', 'pending')
            ->whereDate('followup_date', '<', $today)
            ->orderBy('followup_date', 'asc')
            ->limit(10)
            ->get();

        $todayFollowups = LeadFollowup::with('lead:id,name,phone,lead_type')
            ->where('status', 'pending')
            ->whereDate('followup_date', $today)
            ->orderBy('followup_date', 'asc')
            ->limit(10)
            ->get();

        $tomorrowFollowups = LeadFollowup::with('lead:id,name,phone,lead_type')
            ->where('status', 'pending')
            ->whereDate('followup_date', $tomorrow)
            ->orderBy('followup_date', 'asc')
            ->limit(10)
            ->get();

        // ── Visa Funnel ───────────────────────────────────────────────────
        $visaFunnel = DB::table('lead_visas')
            ->select('document_status', DB::raw('count(*) as total'))
            ->groupBy('document_status')
            ->pluck('total', 'document_status')
            ->toArray();

        $visaFunnelData = [
            'Applications'       => DB::table('lead_visas')->count(),
            'Docs Collected'     => ($visaFunnel['collected'] ?? 0) + ($visaFunnel['complete'] ?? 0) + ($visaFunnel['submitted'] ?? 0),
            'Complete'           => ($visaFunnel['complete'] ?? 0) + ($visaFunnel['submitted'] ?? 0),
            'Submitted'          => $visaFunnel['submitted'] ?? 0,
            'Approved (Won)'     => Lead::whereNull('deleted_at')->where('lead_type', 'visa')->where('status', 'won')->count(),
            'Rejected (Lost)'    => Lead::whereNull('deleted_at')->where('lead_type', 'visa')->where('status', 'lost')->count(),
        ];

        // ── Payment Status (Visa) ─────────────────────────────────────────
        $visaPayment = DB::table('lead_visas')
            ->select('payment_status', DB::raw('count(*) as total'))
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->toArray();

        // ── Missing Documents Alert ──────────────────────────────────────────
        $missingDocLeads = Lead::whereNull('deleted_at')
            ->where('lead_type', 'visa')
            ->whereNotIn('status', ['won', 'lost'])
            ->whereHas('visaDocuments', fn($q) => $q->where('is_mandatory', true)->where('is_collected', false))
            ->with([
                'visa.country',
                'visaDocuments' => fn($q) => $q->where('is_mandatory', true)->where('is_collected', false),
            ])
            ->limit(10)
            ->get();

        // ── Air Ticket Live Leads ─────────────────────────────────────────
        $airTicketLeads = Lead::whereNull('deleted_at')
            ->where('lead_type', 'air_ticket')
            ->whereNotIn('status', ['won', 'lost'])
            ->with(['airTicket', 'assignedEmployee:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // ── Visa Live Applications ────────────────────────────────────────
        $visaLeads = Lead::whereNull('deleted_at')
            ->where('lead_type', 'visa')
            ->whereNotIn('status', ['won', 'lost'])
            ->with(['visa.country', 'assignedEmployee:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // ── Lead Pipeline (Kanban counts) ─────────────────────────────────
        $pipeline = Lead::whereNull('deleted_at')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // ── Monthly Trend (last 6 months) ─────────────────────────────────
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthlyTrend[] = [
                'label' => $month->format('M'),
                'total' => Lead::whereNull('deleted_at')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
                'won'   => Lead::whereNull('deleted_at')
                    ->where('status', 'won')
                    ->whereYear('updated_at', $month->year)
                    ->whereMonth('updated_at', $month->month)
                    ->count(),
            ];
        }

        // ── Won → Project Conversion Status ─────────────────────────────────
        $wonAndConverted = Lead::whereNull('deleted_at')
            ->where('status', 'won')
            ->whereHas('project')
            ->count();

        $wonNotConverted = Lead::whereNull('deleted_at')
            ->where('status', 'won')
            ->whereDoesntHave('project')
            ->count();

        $pendingConversion = Lead::whereNull('deleted_at')
            ->where('status', 'won')
            ->whereDoesntHave('project')
            ->with('customer:id,name')
            ->latest()
            ->take(8)
            ->get();

        $recentConverted = Lead::whereNull('deleted_at')
            ->where('status', 'won')
            ->whereHas('project')
            ->with(['project:id,project_name,status,lead_id', 'customer:id,name'])
            ->latest()
            ->take(8)
            ->get();

        return view('crm.dashboard', compact(
            'summary',
            'byType',
            'overdueFollowups',
            'todayFollowups',
            'tomorrowFollowups',
            'visaFunnelData',
            'visaPayment',
            'airTicketLeads',
            'visaLeads',
            'pipeline',
            'monthlyTrend',
            'missingDocLeads',
            'wonAndConverted',
            'wonNotConverted',
            'pendingConversion',
            'recentConverted'
        ));
    }
}
