<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
/* GET ?what=brief|kpis|decisions|approvals|cash|receivables|payables|pnl|trial-balance|balance-sheet|budget|attendance|patterns|payroll|pipeline|tasks|projects|trend|employee&company=ID */
Http::run(function () {
    Http::auth();
    $company = Http::intq('company');
    $A = new Analytics(Dataset::current($company), $company);
    $what = (string) Http::q('what', 'brief');
    $out = match ($what) {
        'kpis' => $A->kpis(), 'decisions' => $A->decisions(), 'approvals' => $A->approvals(), 'cash' => $A->cash(),
        'receivables' => $A->schedules('receive'), 'payables' => $A->schedules('pay'), 'pnl' => $A->profitAndLoss(Http::q('from') ?: null, Http::q('to') ?: null),
        'trial-balance' => $A->trialBalance(), 'balance-sheet' => $A->balanceSheet(), 'budget' => $A->expensesVsBudget(Http::q('month') ?: null),
        'attendance' => $A->attendanceToday(), 'patterns' => $A->latePatterns((int) (Http::q('days') ?: 30)), 'payroll' => $A->payroll(Http::q('month') ?: null),
        'pipeline' => $A->pipeline(), 'tasks' => $A->tasks(), 'projects' => $A->projects(), 'trend' => $A->revenueTrend(),
        'employee' => (function () use ($A) { $e = $A->findEmployee((string) Http::q('name', '')); return $e ? $A->evaluate((int) $e['id']) : ['error' => 'not found']; })(),
        default => $A->brief(),
    };
    if ($what === 'brief') Memory::logDecisions($out['decisions'], $company);
    Http::json(['ok' => true, 'what' => $what, 'company' => $company, 'source' => Dataset::source(), 'data' => $out]);
});
