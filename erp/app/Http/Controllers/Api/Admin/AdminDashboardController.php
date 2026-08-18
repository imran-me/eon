<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Expense;
use App\Models\Leave;
use App\Models\Notice;
use App\Models\Project;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $today = Carbon::today();
            $now = Carbon::now();

            // ----------------------------------------------------------------
            // 1. TOTAL BALANCE
            // ----------------------------------------------------------------
            $totalBalance = (float) Bank::sum("balance");

            $currentMonthStart = $now->copy()->startOfMonth();
            $currentMonthEnd = $now->copy()->endOfMonth();

            $monthlyCredit = (float) Transaction::whereBetween("payment_date", [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])->sum("credit");

            $monthlyDebit = (float) Transaction::whereBetween("payment_date", [
                $currentMonthStart->toDateString(),
                $currentMonthEnd->toDateString(),
            ])->sum("debit");

            $monthlyNet = $monthlyCredit - $monthlyDebit;
            $lastMonthAmount = round($totalBalance - $monthlyNet, 2);

            if ($lastMonthAmount == 0) {
                $comparePercentage = $totalBalance > 0 ? 100.0 : 0.0;
            } else {
                $comparePercentage = round(
                    (($totalBalance - $lastMonthAmount) /
                        abs($lastMonthAmount)) *
                        100,
                    2,
                );
            }

            $totalBalanceData = [
                "amount" => round($totalBalance, 2),
                "last_month_amount" => $lastMonthAmount,
                "compare_percentage" => $comparePercentage,
                "trend" => $comparePercentage >= 0 ? "up" : "down",
                "currency" => "USD",
            ];

            // ----------------------------------------------------------------
            // 2. FINANCIAL SUMMARY
            // ----------------------------------------------------------------
            $year = $request->input("year", $now->year);
            $month = $request->input("month", $now->month);

            $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
            $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

            $revenue = (float) Transaction::whereBetween("payment_date", [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])->sum("credit");

            $expense = (float) Expense::whereBetween("expense_date", [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])->sum("amount");

            $profit = round($revenue - $expense, 2);
            $profitMargin =
                $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

            $financialSummary = [
                "period" => $periodStart->format("Y-m"),
                "revenue" => round($revenue, 2),
                "expense" => round($expense, 2),
                "profit" => $profit,
                "profit_margin" => $profitMargin,
            ];

            // ----------------------------------------------------------------
            // 3. REVENUE GRAPH (current year, Jan–Dec)
            // ----------------------------------------------------------------
            $currentYear = $now->year;

            $monthlyRevenues = Transaction::selectRaw(
                "MONTH(payment_date) as month_number, SUM(credit) as total_credit",
            )
                ->whereYear("payment_date", $currentYear)
                ->groupBy(DB::raw("MONTH(payment_date)"))
                ->pluck("total_credit", "month_number")
                ->toArray();

            $monthlyExpenses = Expense::selectRaw(
                "MONTH(expense_date) as month_number, SUM(amount) as total_amount",
            )
                ->whereYear("expense_date", $currentYear)
                ->groupBy(DB::raw("MONTH(expense_date)"))
                ->pluck("total_amount", "month_number")
                ->toArray();

            $monthNames = [
                "Jan",
                "Feb",
                "Mar",
                "Apr",
                "May",
                "Jun",
                "Jul",
                "Aug",
                "Sep",
                "Oct",
                "Nov",
                "Dec",
            ];
            $revenueGraph = [];

            for ($m = 1; $m <= 12; $m++) {
                $rev = round((float) ($monthlyRevenues[$m] ?? 0), 2);
                $exp = round((float) ($monthlyExpenses[$m] ?? 0), 2);

                $revenueGraph[] = [
                    "month" => $monthNames[$m - 1],
                    "month_number" => $m,
                    "revenue" => $rev,
                    "expense" => $exp,
                    "profit" => round($rev - $exp, 2),
                ];
            }

            // ----------------------------------------------------------------
            // 4. PROJECT & TASK OVERVIEW
            // ----------------------------------------------------------------
            $totalProjects = Project::count();
            $activeProjects = Project::whereIn("status", [
                "in_progress",
                "active",
            ])->count();
            $completedProjects = Project::where("status", "completed")->count();
            $onHoldProjects = Project::where("status", "on_hold")->count();

            // Collect column IDs by category (lower-cased name)
            $allColumns = DB::table("columns")->get(["id", "name", "position"]);

            $todoColumnIds = [];
            $inProgressColumnIds = [];
            $doneColumnIds = [];

            foreach ($allColumns as $col) {
                $nameLower = strtolower($col->name);

                if (
                    $nameLower === "todo" ||
                    $nameLower === "to do" ||
                    (int) $col->position === 0
                ) {
                    $todoColumnIds[] = $col->id;
                }
                if (str_contains($nameLower, "progress")) {
                    $inProgressColumnIds[] = $col->id;
                }
                if (
                    str_contains($nameLower, "done") ||
                    str_contains($nameLower, "complete")
                ) {
                    $doneColumnIds[] = $col->id;
                }
            }

            $totalTasks = Task::count();
            $pendingTasks = Task::whereIn(
                "column_id",
                array_unique($todoColumnIds),
            )->count();
            $inProgressTasks = Task::whereIn(
                "column_id",
                array_unique($inProgressColumnIds),
            )->count();
            $completedTasks = Task::whereIn(
                "column_id",
                array_unique($doneColumnIds),
            )->count();
            $overdueTasks = Task::whereNotNull("due_date")
                ->where("due_date", "<", $today->toDateString())
                ->count();

            $projectTaskOverview = [
                "projects" => [
                    "total" => $totalProjects,
                    "active" => $activeProjects,
                    "completed" => $completedProjects,
                    "on_hold" => $onHoldProjects,
                ],
                "tasks" => [
                    "total" => $totalTasks,
                    "pending" => $pendingTasks,
                    "in_progress" => $inProgressTasks,
                    "completed" => $completedTasks,
                    "overdue" => $overdueTasks,
                ],
            ];

            // ----------------------------------------------------------------
            // 5. TEAM OVERVIEW
            // ----------------------------------------------------------------
            $totalEmployees = User::where(function ($q) {
                $q->where("is_super_admin", "!=", 1)->orWhereNull(
                    "is_super_admin",
                );
            })->count();

            $activeEmployees = User::where(function ($q) {
                $q->where("is_super_admin", "!=", 1)->orWhereNull(
                    "is_super_admin",
                );
            })
                ->where("status", "active")
                ->count();

            $onLeaveTodayCount = Leave::where("status", "approved")
                ->where("start_date", "<=", $today->toDateString())
                ->where("end_date", ">=", $today->toDateString())
                ->distinct("user_id")
                ->count("user_id");

            $departmentCount = EmployeeProfile::whereNotNull("department_id")
                ->distinct("department_id")
                ->count("department_id");

            $departmentList = Department::select(
                "departments.id",
                "departments.name",
            )
                ->selectRaw("COUNT(employee_profiles.id) as employee_count")
                ->leftJoin(
                    "employee_profiles",
                    "employee_profiles.department_id",
                    "=",
                    "departments.id",
                )
                ->groupBy("departments.id", "departments.name")
                ->orderBy("departments.name")
                ->get()
                ->map(function ($dept) {
                    return [
                        "id" => $dept->id,
                        "name" => $dept->name,
                        "employee_count" => (int) $dept->employee_count,
                    ];
                })
                ->values()
                ->toArray();

            $teamOverview = [
                "total_employees" => $totalEmployees,
                "active" => $activeEmployees,
                "on_leave_today" => $onLeaveTodayCount,
                "departments" => $departmentCount,
                "department_list" => $departmentList,
            ];

            // ----------------------------------------------------------------
            // 6. ACCOUNTS LIST
            // ----------------------------------------------------------------
            $accountsList = Bank::where("status", true)
                ->orderBy("balance", "desc")
                ->get([
                    "id",
                    "name",
                    "account_name",
                    "account_number",
                    "account_type",
                    "bank_type",
                    "currency",
                    "balance",
                    "status",
                    "last_transaction_date",
                    "last_transaction_amount",
                    "last_transaction_type",
                ])
                ->map(function ($bank) {
                    return [
                        "id" => $bank->id,
                        "name" => $bank->name,
                        "account_name" => $bank->account_name,
                        "account_number" => $bank->account_number,
                        "account_type" => $bank->account_type,
                        "bank_type" => $bank->bank_type,
                        "currency" => $bank->currency,
                        "balance" => round((float) $bank->balance, 2),
                        "status" => (bool) $bank->status,
                        "last_transaction_date" => $bank->last_transaction_date,
                        "last_transaction_amount" =>
                            $bank->last_transaction_amount,
                        "last_transaction_type" => $bank->last_transaction_type,
                    ];
                })
                ->values()
                ->toArray();

            // ----------------------------------------------------------------
            // 7. RECENT ACTIVITY
            // ----------------------------------------------------------------
            $recentTransactions = Transaction::with("account")
                ->orderBy("payment_date", "desc")
                ->limit(10)
                ->get()
                ->map(function ($txn) {
                    return [
                        "id" => $txn->id,
                        "type" => $txn->type,
                        "user_type" => $txn->user_type,
                        "payment_date" => $txn->payment_date,
                        "reference_no" => $txn->reference_no,
                        "payment_method" => $txn->payment_method,
                        "debit" => round((float) $txn->debit, 2),
                        "credit" => round((float) $txn->credit, 2),
                        "balance" => round((float) $txn->balance, 2),
                        "remarks" => $txn->remarks,
                        "account_name" => $txn->account
                            ? $txn->account->name
                            : null,
                    ];
                })
                ->values()
                ->toArray();

            $recentExpenses = Expense::with("expense_category")
                ->orderBy("expense_date", "desc")
                ->limit(10)
                ->get()
                ->map(function ($exp) {
                    return [
                        "id" => $exp->id,
                        "title" => $exp->title,
                        "amount" => round((float) $exp->amount, 2),
                        "payment_mode" => $exp->payment_mode,
                        "expense_date" => $exp->expense_date,
                        "status" => $exp->status,
                        "category_name" => $exp->expense_category
                            ? $exp->expense_category->name
                            : null,
                        "reference" => $exp->reference,
                    ];
                })
                ->values()
                ->toArray();

            $recentActivity = [
                "transactions" => $recentTransactions,
                "expenses" => $recentExpenses,
            ];

            // ----------------------------------------------------------------
            // 8. QUICK ACTIONS
            // ----------------------------------------------------------------
            $quickActions = [
                [
                    "key" => "add_transaction",
                    "label" => "Add Transaction",
                    "icon" => "transaction",
                    "endpoint" => "/api/admin/quick-actions/transaction",
                    "method" => "POST",
                ],
                [
                    "key" => "add_expense",
                    "label" => "Add Expense",
                    "icon" => "expense",
                    "endpoint" => "/api/admin/quick-actions/expense",
                    "method" => "POST",
                ],
                [
                    "key" => "create_task",
                    "label" => "Create Task",
                    "icon" => "task",
                    "endpoint" => "/api/admin/tasks",
                    "method" => "POST",
                ],
                [
                    "key" => "post_notice",
                    "label" => "Post Notice",
                    "icon" => "notice",
                    "endpoint" => "/api/admin/quick-actions/notice",
                    "method" => "POST",
                ],
            ];

            // ----------------------------------------------------------------
            // RESPONSE
            // ----------------------------------------------------------------
            return response()->json(
                [
                    "success" => true,
                    "message" => "Admin dashboard data fetched successfully.",
                    "data" => [
                        "total_balance" => $totalBalanceData,
                        "financial_summary" => $financialSummary,
                        "revenue_graph" => $revenueGraph,
                        "project_task_overview" => $projectTaskOverview,
                        "team_overview" => $teamOverview,
                        "accounts_list" => $accountsList,
                        "recent_activity" => $recentActivity,
                        "quick_actions" => $quickActions,
                    ],
                ],
                200,
            );
        } catch (\Throwable $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to fetch admin dashboard data.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
