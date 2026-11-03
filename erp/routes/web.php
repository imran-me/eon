<?php

use App\Events\TestEvent;
use App\Http\Controllers\AdvanceSalaryController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AirlineController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyDashboardController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Dashboard\AccountController;
use App\Http\Controllers\Dashboard\BankControllerV2;
use App\Http\Controllers\Dashboard\BankTransferController;
use App\Http\Controllers\Dashboard\CommentController;
use App\Http\Controllers\Dashboard\ContractFileCategoryController;
use App\Http\Controllers\Dashboard\ContractFileController;
use App\Http\Controllers\Dashboard\ContractFileSaleController;
use App\Http\Controllers\Dashboard\ContractFileSetupController;
use App\Http\Controllers\Dashboard\ContractFlightAccountsController;
use App\Http\Controllers\Dashboard\ContractFlightChangeCorrectionController;
use App\Http\Controllers\Dashboard\ContractFlightMessageController;
use App\Http\Controllers\Dashboard\ContractFlightPassengerController;
use App\Http\Controllers\Dashboard\ContractFlightRebookController;
use App\Http\Controllers\Dashboard\ContractFlightStageController;
use App\Http\Controllers\Dashboard\ContractFlightTrailController;
use App\Http\Controllers\Dashboard\ContractManageController;
use App\Http\Controllers\Dashboard\ContractTypeController;
use App\Http\Controllers\Dashboard\CrmDashboardController;
use App\Http\Controllers\Dashboard\CustomerRoleController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DealManageController;
use App\Http\Controllers\Dashboard\DemoDataController;
use App\Http\Controllers\Dashboard\EmailMarketingController;
use App\Http\Controllers\Dashboard\EstimateController;
use App\Http\Controllers\Dashboard\FinancingController;
use App\Http\Controllers\Dashboard\FlightCategoryController;
use App\Http\Controllers\Dashboard\FlightCategoryTypeController;
use App\Http\Controllers\Dashboard\FlightOfficerController;
use App\Http\Controllers\Dashboard\FlightPricePresetController;
use App\Http\Controllers\Dashboard\FlightScheduleController;
use App\Http\Controllers\Dashboard\HeadlineNoticeController;
use App\Http\Controllers\Dashboard\JournalController;
use App\Http\Controllers\Dashboard\LeadFollowupController;
use App\Http\Controllers\Dashboard\LeadManagerController;
use App\Http\Controllers\Dashboard\LeadReminderController;
use App\Http\Controllers\Dashboard\LeadSourceController;
use App\Http\Controllers\Dashboard\ManageFlightController;
use App\Http\Controllers\Dashboard\ManualAttendanceApprovalController;
use App\Http\Controllers\Dashboard\MonthlyProfitController;
use App\Http\Controllers\Dashboard\OfficeTodoController;
use App\Http\Controllers\Dashboard\OperationStatusTrackerController;
use App\Http\Controllers\Dashboard\OtherServiceTypeController;
use App\Http\Controllers\Dashboard\OtherVisaServiceController;
use App\Http\Controllers\Dashboard\PartyDiscountController;
use App\Http\Controllers\Dashboard\PartyInvoiceImportController;
use App\Http\Controllers\Dashboard\PartyStatementController;
use App\Http\Controllers\Dashboard\PartyTransactionImportController;
use App\Http\Controllers\Dashboard\PassportHolderCategoryControllerV2;
use App\Http\Controllers\Dashboard\PassportHolderControllerV2;
use App\Http\Controllers\Dashboard\PaymentScheduleController;
use App\Http\Controllers\Dashboard\PayrolController;
use App\Http\Controllers\Dashboard\PortalManagementController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\ProjectCategoryController;
use App\Http\Controllers\Dashboard\ProjectManageController;
use App\Http\Controllers\Dashboard\PromotionController;
use App\Http\Controllers\Dashboard\ProposalManageController;
use App\Http\Controllers\Dashboard\RequireTypeController;
use App\Http\Controllers\Dashboard\ResignationController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\RouteManagementController;
use App\Http\Controllers\Dashboard\SmsMarketingController;
use App\Http\Controllers\Dashboard\SupportTicketController;
use App\Http\Controllers\Dashboard\TaskProjectController;
use App\Http\Controllers\Dashboard\TicketAccountsController;
use App\Http\Controllers\Dashboard\TicketBookingController;
use App\Http\Controllers\Dashboard\TicketChangeController;
use App\Http\Controllers\Dashboard\TicketDashboardController;
use App\Http\Controllers\Dashboard\TicketDepartmentController;
use App\Http\Controllers\Dashboard\TicketDirectSaleController;
use App\Http\Controllers\Dashboard\TicketImportController;
use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Http\Controllers\Dashboard\TicketPurgeController;
use App\Http\Controllers\Dashboard\TicketSaleController;
use App\Http\Controllers\Dashboard\TicketTrailController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\UserRoleSettingController;
use App\Http\Controllers\Dashboard\VendorController;
use App\Http\Controllers\Dashboard\VisaAccountsController;
use App\Http\Controllers\Dashboard\VisaDashboardController;
use App\Http\Controllers\Dashboard\VisaSaleController;
use App\Http\Controllers\Dashboard\VoucherController;
use App\Http\Controllers\Dashboard\WhatsappMarketingController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmployeeLedgerController;
use App\Http\Controllers\EmployeeReimbursementController;
use App\Http\Controllers\EmployeeRequestController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\ExpenseBudgetController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseClassificationController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseDepartmentController;
use App\Http\Controllers\ExpenseSubCategoryController;
use App\Http\Controllers\ExpenseSubscriptionController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartyTypeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\Report\AccountingReportController;
use App\Http\Controllers\Report\ContractFlightReportController;
use App\Http\Controllers\Report\PayrollReportController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\TaskReportController;
use App\Http\Controllers\RequireAssignmentController;
use App\Http\Controllers\SalaryTemplateController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleRecordController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Task\BoardController;
use App\Http\Controllers\Task\ColumnController;
use App\Http\Controllers\Task\EmployeeTaskOversightController;
use App\Http\Controllers\Task\LabelController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Task\WorkSpaceUserController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserShortcutController;
use App\Http\Controllers\VisaCategoryController;
use App\Http\Controllers\VisaProcessingController;
use App\Http\Middleware\EnsurePanelRoleIsPermitted;
use App\Models\Board;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

Route::get('/', function () {
    return Auth::check()
       ? redirectToRoleDashboard(Auth::user())
       : redirect()->route('login');
});

Route::get('server_status', [ApiController::class, 'serverStatus']);
Route::get('device/{data}', [ApiController::class, 'device']);
Route::get('attendance_log', [ApiController::class, 'attendanceLog']);

// SSO: DM Portal auto-login redirect (auth required)
Route::middleware('auth')->get('/sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');

// Login routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// 2FA routes (verify after login — no auth required yet)
Route::get('/two-factor/verify', [TwoFactorController::class, 'showVerify'])->name('two-factor.verify');
Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify.post');

// 2FA setup/disable routes (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

Route::get('server_status', [ApiAttendanceController::class, 'serverStatus']);
Route::get('device/{data}', [ApiAttendanceController::class, 'device']);
Route::get('attendance_log/{data}', [ApiAttendanceController::class, 'attendanceLog']);

$roles = Role::pluck('name')->map(fn ($name) => Str::slug($name))->implode('|');
// Super Admin routes
// Super Admin routes
Route::middleware(['auth', EnsurePanelRoleIsPermitted::class])
    ->prefix('{role}')
    ->name('role.')
    ->where(['role' => $roles])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/todo-list', [DashboardController::class, 'todoList'])->name('dashboard.todo-list');
        Route::get('manual-attendance-requests', [ManualAttendanceApprovalController::class, 'index'])->name('manual-attendance-requests.index');
        Route::patch('manual-attendance-requests/{manualAttendanceRequest}/approve', [ManualAttendanceApprovalController::class, 'approve'])->name('manual-attendance-requests.approve');
        Route::patch('manual-attendance-requests/{manualAttendanceRequest}/reject', [ManualAttendanceApprovalController::class, 'reject'])->name('manual-attendance-requests.reject');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('change-password', [LoginController::class, 'updatePassword'])->name('change.password');
        Route::get('user/documents', [UserController::class, 'documents'])->name('user.documents');
        Route::post('user/{user}/documents', [UserController::class, 'updateDocuments'])->name('user.documents.update');
        Route::get('user/{user}/summary', [UserController::class, 'summary'])->name('user.summary');
        Route::get('payroll/employee-ledger/{user}', [UserController::class, 'summary'])->name('employee-ledger.show');
        Route::post('payroll/employee-ledger/{user}/send', [UserController::class, 'sendEmployeeLedger'])->name('employee-ledger.send');
        Route::get('user/{user}/salary-transaction-report/print', [UserController::class, 'salaryTransactionReportPrint'])->name('user.salary-transaction-report.print');
        Route::get('user/{user}/leave-encashment-report/print', [UserController::class, 'leaveEncashmentReportPrint'])->name('user.leave-encashment-report.print');
        Route::get('user/{user}/payslips-report/print', [UserController::class, 'payslipsReportPrint'])->name('user.payslips-report.print');
        Route::get('user/{user}/attendance-report/print', [UserController::class, 'attendanceReportPrint'])->name('user.attendance-report.print');
        Route::post('user/{user}/ledger/bonus', [EmployeeLedgerController::class, 'storeBonus'])->name('user.ledger.bonus');
        Route::post('user/{user}/ledger/opening-balance', [EmployeeLedgerController::class, 'storeOpeningBalance'])->name('user.ledger.opening-balance');
        Route::post('user/{user}/ledger/{entry}/date-time', [EmployeeLedgerController::class, 'updatePaymentDateTime'])->name('user.ledger.update-payment-date-time');
        Route::post('user/{user}/leave-encashment-opening', [EmployeeLedgerController::class, 'storeLeaveEncashmentOpening'])->name('user.leave-encashment-opening');
        Route::post('user/{user}/leave-encashment-exit-payout', [EmployeeLedgerController::class, 'storeExitLeaveEncashment'])->name('user.leave-encashment-exit-payout');
        Route::post('user/{user}/pay-due-amount', [PaymentScheduleController::class, 'payDueAmount'])->name('user.pay-due-amount');
        Route::post('employee-payments/{payment}/reverse', [PaymentScheduleController::class, 'reverseEmployeePayment'])->name('employee-payments.reverse');
        Route::get('user/search', [UserController::class, 'search'])->name('user.search');
        Route::get('user/print', [UserController::class, 'printList'])->name('user.print');
        Route::resource('user', UserController::class);
        Route::resource('promotions', PromotionController::class)->except('show');
        Route::post('promotions/approve/{id}', [PromotionController::class, 'approve'])->name('promotions.approve');
        Route::post('promotions/reject/{id}', [PromotionController::class, 'reject'])->name('promotions.reject');
        // User Role Setting (owner, 2026-11-01): the new, separate role-permission system — super admin only
        Route::get('user-role-settings', [UserRoleSettingController::class, 'index'])->name('user-role-settings.index');
        Route::get('user-role-settings/employees', [UserRoleSettingController::class, 'employees'])->name('user-role-settings.employees');
        Route::get('user-role-settings/people', [UserRoleSettingController::class, 'people'])->name('user-role-settings.people');
        Route::get('user-role-settings/saved', [UserRoleSettingController::class, 'saved'])->name('user-role-settings.saved');
        Route::get('user-role-settings/{user}', [UserRoleSettingController::class, 'show'])->whereNumber('user')->name('user-role-settings.show');
        Route::post('user-role-settings/{user}', [UserRoleSettingController::class, 'save'])->whereNumber('user')->name('user-role-settings.save');
        Route::post('user-role-settings/{user}/apply', [UserRoleSettingController::class, 'apply'])->whereNumber('user')->name('user-role-settings.apply');
        Route::post('user-role-settings/master', [UserRoleSettingController::class, 'master'])->name('user-role-settings.master');
        Route::delete('user-role-settings/{user}', [UserRoleSettingController::class, 'destroy'])->whereNumber('user')->name('user-role-settings.destroy');
        Route::resource('passport-holder', PassportHolderControllerV2::class);
        Route::resource('passport-holder-category', PassportHolderCategoryControllerV2::class);
        Route::resource('visa-category', VisaCategoryController::class)->except('show');
        Route::resource('visa', VisaProcessingController::class)->except('show');
        // Visa Sales — static routes must come before the {visaSale} binding
        Route::get('visa-sales/applications', [VisaSaleController::class, 'fetchApplications'])->name('visa-sales.applications');
        Route::get('visa-sales/other-services', [VisaSaleController::class, 'fetchOtherServices'])->name('visa-sales.other-services');
        Route::get('visa-sales/{visaSale}/voucher', [VisaSaleController::class, 'voucherPrint'])->name('visa-sales.voucher');
        Route::post('visa-sales/{visaSale}/email', [VisaSaleController::class, 'emailVoucher'])->name('visa-sales.email');
        Route::get('visa-sales/{visaSale}/edit', [VisaSaleController::class, 'edit'])->name('visa-sales.edit');
        Route::put('visa-sales/{visaSale}', [VisaSaleController::class, 'update'])->name('visa-sales.update');
        Route::patch('visa-sales/{visaSale}/payment', [VisaSaleController::class, 'makePayment'])->name('visa-sales.payment');
        Route::get('visa-sales/{visaSale}', [VisaSaleController::class, 'show'])->name('visa-sales.show');
        Route::get('visa-sales', [VisaSaleController::class, 'index'])->name('visa-sales.index');
        Route::post('visa-sales', [VisaSaleController::class, 'store'])->name('visa-sales.store');
        Route::delete('visa-sales/{visaSale}', [VisaSaleController::class, 'destroy'])->name('visa-sales.destroy');
        // ── Comments (polymorphic — ticket_sale, visa_sale, contract_flight, contract_file) ──
        Route::get('comments', [CommentController::class, 'list'])->name('comments.list');
        Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        // Business Operations control tower — reads the existing travel records
        // and stores only cross-module accountability/deadline metadata.
        Route::get('operation-status-tracker', [OperationStatusTrackerController::class, 'index'])
            ->name('operation-status-tracker.index');
        Route::get('operation-status-tracker/print', [OperationStatusTrackerController::class, 'print'])
            ->name('operation-status-tracker.print');
        Route::patch('operation-status-tracker/{type}/{id}', [OperationStatusTrackerController::class, 'update'])
            ->where(['type' => 'visa|file|ticket', 'id' => '[0-9]+'])
            ->name('operation-status-tracker.update');
        Route::post('operation-status-tracker/{type}/{id}/assignees', [OperationStatusTrackerController::class, 'assign'])
            ->where(['type' => 'visa|file|ticket', 'id' => '[0-9]+'])
            ->name('operation-status-tracker.assign');
        Route::delete('operation-status-tracker/{type}/{id}/assignees/{assignee}', [OperationStatusTrackerController::class, 'unassign'])
            ->where(['type' => 'visa|file|ticket', 'id' => '[0-9]+', 'assignee' => '[0-9]+'])
            ->name('operation-status-tracker.unassign');
        Route::post('operation-status-tracker/{type}/{id}/notes', [OperationStatusTrackerController::class, 'addNote'])
            ->where(['type' => 'visa|file|ticket', 'id' => '[0-9]+'])
            ->name('operation-status-tracker.note');

        // Visa Dashboard (2026-10-13): open applications as cards, finished ones below — read-only.
        Route::get('visa-dashboard', [VisaDashboardController::class, 'index'])->name('visa-dashboard');
        // Visa Accounts (2026-10-12): P&L, Agent / Vendor Ledger tabs, transactions, journal — read-only.
        Route::get('visa-accounts', [VisaAccountsController::class, 'index'])->name('visa-accounts.index');
        Route::get('visa-accounts/ledger/{ledger}', [VisaAccountsController::class, 'ledger'])->whereIn('ledger', ['agents', 'vendors'])->name('visa-accounts.ledger');
        Route::get('visa-doc-tracker', [VisaProcessingController::class, 'docTracker'])->name('visa.doc-tracker');
        Route::post('visa/{visa}/doc-toggle', [VisaProcessingController::class, 'toggleDocStatus'])->name('visa.doc-toggle');
        Route::get('visa/{visa}/attachments', [VisaProcessingController::class, 'getAttachments'])->name('visa.attachments');
        Route::get('visa/{visa}/details', [VisaProcessingController::class, 'details'])->name('visa.details');
        Route::delete('visa/{visa}/attachment/{attachment}', [VisaProcessingController::class, 'deleteAttachment'])->name('visa.attachment.delete');
        Route::patch('visa/{visa}/status', [VisaProcessingController::class, 'updateStatus'])->name('visa.status');
        Route::post('visa/{visa}/pay-vendor', [VisaProcessingController::class, 'payVendor'])->name('visa.pay-vendor');
        Route::resource('other-visa-services', OtherVisaServiceController::class)->except(['show', 'create', 'edit']);
        Route::resource('other-service-types', OtherServiceTypeController::class)->except(['show', 'create', 'edit']);
        Route::resource('contract-file-categories', ContractFileCategoryController::class)->except(['show', 'create', 'edit']);
        // Contract File → Setup (2026-10-25): requirement builder, price presets, visa types; match = what the file entry fills.
        Route::get('contract-file-setup/match', [ContractFileSetupController::class, 'match'])->name('contract-file-setup.match');
        Route::get('contract-file-setup/{kind}', [ContractFileSetupController::class, 'index'])->whereIn('kind', ContractFileSetupController::KINDS)->name('contract-file-setup.index');
        Route::post('contract-file-setup/{kind}', [ContractFileSetupController::class, 'store'])->whereIn('kind', ContractFileSetupController::KINDS)->name('contract-file-setup.store');
        Route::put('contract-file-setup/{kind}/{id}', [ContractFileSetupController::class, 'update'])->whereIn('kind', ContractFileSetupController::KINDS)->whereNumber('id')->name('contract-file-setup.update');
        Route::delete('contract-file-setup/{kind}/{id}', [ContractFileSetupController::class, 'destroy'])->whereIn('kind', ContractFileSetupController::KINDS)->whereNumber('id')->name('contract-file-setup.destroy');
        Route::get('contract-files/dashboard', [ContractFileController::class, 'dashboard'])->name('contract-files.dashboard');
        Route::get('contract-files/board', [ContractFileController::class, 'board'])->name('contract-files.board');
        Route::get('contract-files/documents/{document}', [ContractFileController::class, 'document'])->name('contract-files.document');
        Route::patch('contract-files/documents/{document}', [ContractFileController::class, 'updateDocument'])->name('contract-files.document-status');
        Route::get('contract-files/accounts', [ContractFileController::class, 'accounts'])->name('contract-files.accounts');
        Route::get('contract-files/agents/{client}/files', [ContractFileController::class, 'agentFiles'])->name('contract-files.agent-files');
        Route::resource('contract-files', ContractFileController::class)->except(['show', 'create', 'edit']);
        Route::get('contract-files/{contractFile}/details', [ContractFileController::class, 'show'])->name('contract-files.show');
        Route::patch('contract-files/{contractFile}/stage', [ContractFileController::class, 'updateStage'])->name('contract-files.stage');
        Route::patch('contract-files/{contractFile}/exception', [ContractFileController::class, 'updateException'])->name('contract-files.exception');
        Route::post('contract-files/{contractFile}/money-action', [ContractFileController::class, 'moneyAction'])->name('contract-files.money-action');
        Route::get('contract-file-sales/{contractFileSale}/voucher', [ContractFileSaleController::class, 'voucherPrint'])->name('contract-file-sales.voucher');
        Route::patch('contract-file-sales/{contractFileSale}/payment', [ContractFileSaleController::class, 'makePayment'])->name('contract-file-sales.payment');
        // Historical invoices remain readable and payable; new charges belong to each file.
        Route::resource('contract-file-sales', ContractFileSaleController::class)->only(['index', 'show']);
        Route::post('contract-files/{contractFile}/pay-vendor', [ContractFileController::class, 'payVendor'])->name('contract-files.pay-vendor');
        Route::resource('flight-category-types', FlightCategoryTypeController::class)->except(['show', 'create', 'edit']);
        Route::get('flight-price-presets/match', [FlightPricePresetController::class, 'match'])->name('flight-price-presets.match');
        Route::resource('flight-price-presets', FlightPricePresetController::class)->except(['show', 'create', 'edit']);
        Route::resource('flight-officers', FlightOfficerController::class)->except(['show', 'create', 'edit']);
        Route::resource('flight-categories', FlightCategoryController::class)->except(['show', 'create', 'edit']);
        Route::resource('flight-schedules', FlightScheduleController::class)->except(['show', 'create', 'edit']);
        // Contract-flight revenue now posts from each passenger's Agent Price.
        // Keep the old list URL as a bookmark-safe redirect, but expose no
        // create/update/delete routes: using both flows billed the same agent
        // twice and recognised the same revenue through two sources.
        Route::get('contract-flight-sales', function (string $role) {
            return redirect()
                ->route('role.contract-flights.index', ['role' => $role])
                ->with('info', 'Contract flight sales are now recorded from each passenger Agent Price.');
        })->name('contract-flight-sales.index');
        Route::get('contract-flights/dashboard', [ManageFlightController::class, 'dashboard'])->name('contract-flights.dashboard');
        Route::get('contract-flights/accounts', [ContractFlightAccountsController::class, 'index'])->name('contract-flights.accounts');
        Route::get('contract-flights/accounts/share-link', [ContractFlightAccountsController::class, 'shareLink'])->name('contract-flights.accounts.share-link');
        Route::get('contract-flights/accounts/ledger/{ledger}', [ContractFlightAccountsController::class, 'ledger'])->whereIn('ledger', ['agents', 'vendors', 'company'])->name('contract-flights.accounts.ledger');
        Route::get('contract-flights/messages', [ContractFlightMessageController::class, 'index'])->name('contract-flights.messages');
        Route::get('contract-flights/messages/recipients', [ContractFlightMessageController::class, 'recipients'])->name('contract-flights.messages.recipients');
        Route::post('contract-flights/messages/preview', [ContractFlightMessageController::class, 'preview'])->name('contract-flights.messages.preview');
        Route::post('contract-flights/messages/send', [ContractFlightMessageController::class, 'send'])->name('contract-flights.messages.send');
        Route::post('contract-flights/messages/rules', [ContractFlightMessageController::class, 'saveRules'])->name('contract-flights.messages.rules');
        Route::get('contract-flights/print-preview', [ManageFlightController::class, 'printPreview'])->name('contract-flights.print-preview');
        Route::get('contract-flights/{contractFlight}/invoice', [ManageFlightController::class, 'invoice'])->name('contract-flights.invoice');
        Route::post('contract-flights/{contractFlight}/pay-vendor', [ManageFlightController::class, 'payVendor'])->name('contract-flights.pay-vendor');
        Route::resource('contract-flights', ManageFlightController::class)->except(['show', 'create', 'edit']);
        Route::get('contract-flights/{contractFlight}/passengers', [ContractFlightPassengerController::class, 'index'])->name('contract-flight-passengers.index');
        Route::get('contract-flight-board', [ContractFlightPassengerController::class, 'board'])->name('contract-flight-board.index');
        Route::put('contract-flight-stages', [ContractFlightStageController::class, 'sync'])->name('contract-flight-stages.sync');
        Route::patch('contract-flight-passengers/{contractFlightPassenger}/stage', [ContractFlightPassengerController::class, 'updateStage'])->name('contract-flight-passengers.stage');
        Route::patch('contract-flight-passengers/{contractFlightPassenger}', [ContractFlightPassengerController::class, 'update'])->name('contract-flight-passengers.update');
        Route::get('contract-flight-passengers/{contractFlightPassenger}/ticket-purchases', [ContractFlightPassengerController::class, 'searchTicketPurchases'])->name('contract-flight-passengers.ticket-purchases');
        // Static segment ahead of the {contractFlightPassenger} routes below,
        // same reasoning as contract-flight-holders above.
        Route::get('contract-flight-passengers/search-flights', [ContractFlightPassengerController::class, 'searchFlights'])->name('contract-flight-passengers.search-flights');
        Route::patch('contract-flight-passengers/{contractFlightPassenger}/move-flight', [ContractFlightPassengerController::class, 'moveFlight'])->name('contract-flight-passengers.move-flight');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/ticket-adjustment', [ContractFlightPassengerController::class, 'ticketAdjustment'])->name('contract-flight-passengers.ticket-adjustment');
        // A family passenger's unused return (2026-10-14, App\Services\ReturnCancel).
        Route::post('contract-flight-passengers/{contractFlightPassenger}/cancel-return', [ContractFlightPassengerController::class, 'cancelReturn'])->name('contract-flight-passengers.cancel-return');
        // One lane per ticket with its history; lane 1 decides his flight (2026-10-08). Read-only.
        Route::get('contract-flight-passengers/{contractFlightPassenger}/lanes', [ContractFlightPassengerController::class, 'lanes'])->name('contract-flight-passengers.lanes');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/lane-new-ticket', [ContractFlightPassengerController::class, 'laneNewTicket'])->name('contract-flight-passengers.lane-new-ticket');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/correct-refund', [ContractFlightPassengerController::class, 'correctRefund'])->name('contract-flight-passengers.correct-refund');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/undo-change', [ContractFlightPassengerController::class, 'undoChange'])->name('contract-flight-passengers.undo-change');
        // One passenger off a flight into Cancelled, then onto another flight
        // (reschedule / reissue / new ticket, with a charge) or back (Undo).
        Route::patch('contract-flight-passengers/{contractFlightPassenger}/cancel-from-flight', [ContractFlightRebookController::class, 'cancel'])->name('contract-flight-passengers.cancel-from-flight');
        Route::get('contract-flight-passengers/{contractFlightPassenger}/reassign-flights', [ContractFlightRebookController::class, 'flights'])->name('contract-flight-passengers.reassign-flights');
        Route::patch('contract-flight-passengers/{contractFlightPassenger}/reassign', [ContractFlightRebookController::class, 'reassign'])->name('contract-flight-passengers.reassign');
        Route::patch('contract-flight-passengers/{contractFlightPassenger}/offload', [ContractFlightRebookController::class, 'offload'])->name('contract-flight-passengers.offload');
        // Suspend withdraw — another vendor lifts an airline / portal suspension (2026-11-02)
        Route::post('contract-flight-passengers/{contractFlightPassenger}/suspend-withdraw', [\App\Http\Controllers\Dashboard\ContractFlightSuspendWithdrawController::class, 'store'])->name('contract-flight-passengers.suspend-withdraw');
        Route::patch('contract-flight-passengers/{contractFlightPassenger}/restore', [ContractFlightRebookController::class, 'restore'])->name('contract-flight-passengers.restore');
        Route::get('contract-flight-passengers/{contractFlightPassenger}/undo-preview', [ContractFlightRebookController::class, 'undoPreview'])->name('contract-flight-passengers.undo-preview');
        Route::patch('contract-flight-passenger-changes/{contractFlightPassengerChange}/charge', [ContractFlightRebookController::class, 'updateCharge'])->name('contract-flight-passenger-changes.charge');
        // Correct a posted entry — amounts, seller, agent, who bears, by added rows only (super admin, 2026-10-19)
        Route::get('contract-flight-passenger-changes/{contractFlightPassengerChange}/correction', [ContractFlightChangeCorrectionController::class, 'show'])->name('contract-flight-passenger-changes.correction');
        Route::post('contract-flight-passenger-changes/{contractFlightPassengerChange}/correction/preview', [ContractFlightChangeCorrectionController::class, 'preview'])->name('contract-flight-passenger-changes.correction.preview');
        Route::post('contract-flight-passenger-changes/{contractFlightPassengerChange}/correction', [ContractFlightChangeCorrectionController::class, 'apply'])->name('contract-flight-passenger-changes.correction.apply');
        // Contract Flight Trail — every entry of one passenger, edited by REPLACING its rows (super admin, 2026-11-01)
        Route::get('contract-flight-passengers/{contractFlightPassenger}/trail', [ContractFlightTrailController::class, 'show'])->name('contract-flight-trail.show');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/trail/preview', [ContractFlightTrailController::class, 'preview'])->name('contract-flight-trail.preview');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/trail', [ContractFlightTrailController::class, 'apply'])->name('contract-flight-trail.apply');
        // Keyed on the passport holder, not a saved passenger — the Add Flight
        // modal picks tickets before the flight (and therefore the passenger)
        // exists. Static segment sits ahead of the {contractFlightPassenger}
        // routes above so it can never be swallowed by that wildcard.
        Route::get('contract-flight-holders/{passportHolder}/ticket-purchases', [ContractFlightPassengerController::class, 'holderTicketPurchases'])->name('contract-flight-holders.ticket-purchases');
        // Is this passport waiting under Cancelled? The builder fills his card from it.
        Route::get('contract-flight-holders/{passportHolder}/off-flight', [ContractFlightPassengerController::class, 'offFlightForHolder'])->name('contract-flight-holders.off-flight');
        Route::post('contract-flight-passengers/{contractFlightPassenger}/link-ticket', [ContractFlightPassengerController::class, 'linkTicket'])->name('contract-flight-passengers.link-ticket');
        Route::delete('contract-flight-passengers/{contractFlightPassenger}/link-ticket/{ticketPurchase}', [ContractFlightPassengerController::class, 'unlinkTicket'])->name('contract-flight-passengers.unlink-ticket');
        // Party print pages must stay above the resources, otherwise
        // `vendor/print` would match the resource's `vendor/{vendor}` show route.
        Route::get('vendor/print', [VendorController::class, 'print'])->name('vendor.print');
        Route::get('agent/print', [AgentController::class, 'print'])->name('agent.print');
        Route::get('customer/print', [CustomerRoleController::class, 'print'])->name('customer.print');
        Route::resource('vendor', VendorController::class);
        Route::resource('agent', AgentController::class);
        Route::resource('customer', CustomerRoleController::class);
        // Party Statement is four screens under one nav: the dashboard, then a
        // ledger per party type. `index` keeps its name — the sidebar and the
        // bank dashboard's beneficiary codes link to it, and with ?party_id= it
        // forwards to whichever ledger that party belongs on.
        Route::get('party-statement', [PartyStatementController::class, 'index'])->name('party-statement.index');
        Route::get('party-statement/agents', [PartyStatementController::class, 'agents'])->name('party-statement.agents');
        Route::get('party-statement/clients', [PartyStatementController::class, 'clients'])->name('party-statement.clients');
        Route::get('party-statement/vendors', [PartyStatementController::class, 'vendors'])->name('party-statement.vendors');
        Route::get('party-statement/company', [PartyStatementController::class, 'company'])->name('party-statement.company');
        Route::get('party-statement/activity', [PartyStatementController::class, 'activity'])->name('party-statement.activity');
        Route::get('party-statement/activity/print', [PartyStatementController::class, 'printActivity'])->name('party-statement.activity-print');
        Route::post('party-statement/company/funds', [PartyStatementController::class, 'companyFunds'])->name('party-statement.company-funds');
        Route::get('party-statement/search', [PartyStatementController::class, 'search'])->name('party-statement.search');
        Route::get('party-statement/load', [PartyStatementController::class, 'load'])->name('party-statement.load');
        Route::post('party-statement/transactions/{transaction}/date', [PartyStatementController::class, 'updateTransactionDate'])->name('party-statement.transaction-date');
        Route::get('party-statement/unpaid-invoices', [PartyStatementController::class, 'unpaidInvoices'])->name('party-statement.unpaid-invoices');
        Route::post('party-statement/payment', [PartyStatementController::class, 'recordPayment'])->name('party-statement.payment');
        Route::get('party-statement/discounts', [PartyDiscountController::class, 'index'])->name('party-statement.discounts');
        Route::post('party-statement/discounts', [PartyDiscountController::class, 'store'])->name('party-statement.discounts.store');
        Route::post('party-statement/discounts/overall', [PartyDiscountController::class, 'storeOverall'])->name('party-statement.discounts.overall');
        Route::post('party-statement/discounts/batch/{batch}/reverse', [PartyDiscountController::class, 'reverseBatch'])->name('party-statement.discounts.batch-reverse');
        Route::post('party-statement/discounts/{discount}/reverse', [PartyDiscountController::class, 'reverse'])->name('party-statement.discounts.reverse');
        Route::post('party-statement/vendor-refund', [PartyStatementController::class, 'recordVendorRefund'])->name('party-statement.vendor-refund');
        Route::post('party-statement/invoice', [PartyStatementController::class, 'createInvoice'])->name('party-statement.invoice');
        Route::post('party-statement/opening-balance', [PartyStatementController::class, 'setOpeningBalance'])->name('party-statement.opening-balance');
        Route::post('party-statement/send-statement', [PartyStatementController::class, 'sendStatement'])->name('party-statement.send-statement');
        Route::post('party-statement/send-reminder', [PartyStatementController::class, 'sendReminder'])->name('party-statement.send-reminder');
        Route::get('party-statement/export/excel', [PartyStatementController::class, 'exportExcel'])->name('party-statement.excel');
        Route::get('party-statement/export/pdf', [PartyStatementController::class, 'exportPdf'])->name('party-statement.pdf');
        // The sales invoice for an agent / client — ticked entries or a range (2026-10-14).
        Route::get('party-statement/sales-invoice', [PartyStatementController::class, 'salesInvoice'])->name('party-statement.sales-invoice');
        Route::get('party-statement/sales-invoice/lines', [PartyStatementController::class, 'salesInvoiceLines'])->name('party-statement.sales-invoice-lines');
        Route::post('party-statement/invoice-branding', [PartyStatementController::class, 'invoiceBranding'])->name('party-statement.invoice-branding');
        Route::get('party-statement/print', [PartyStatementController::class, 'printFeed'])->name('party-statement.print');
        Route::get('party-statement/parties/print', [PartyStatementController::class, 'printParties'])->name('party-statement.parties-print');
        Route::get('party-statement/invoice/{partyInvoice}/pdf', [PartyStatementController::class, 'invoicePdf'])->name('party-statement.invoice-pdf');
        Route::get('party-transaction-import', [PartyTransactionImportController::class, 'index'])->name('party-transaction-import.index');
        Route::get('party-transaction-import/template', [PartyTransactionImportController::class, 'template'])->name('party-transaction-import.template');
        Route::post('party-transaction-import', [PartyTransactionImportController::class, 'store'])->name('party-transaction-import.store');
        Route::get('party-transaction-import/{partyTransactionImportBatch}', [PartyTransactionImportController::class, 'show'])->name('party-transaction-import.show');
        Route::patch('party-transaction-import/{partyTransactionImportBatch}', [PartyTransactionImportController::class, 'update'])->name('party-transaction-import.update');
        Route::post('party-transaction-import/{partyTransactionImportBatch}/approve', [PartyTransactionImportController::class, 'approve'])->name('party-transaction-import.approve');
        Route::post('party-transaction-import/{partyTransactionImportBatch}/reject', [PartyTransactionImportController::class, 'reject'])->name('party-transaction-import.reject');
        Route::get('party-invoice-import/template', [PartyInvoiceImportController::class, 'template'])->name('party-invoice-import.template');
        Route::post('party-invoice-import', [PartyInvoiceImportController::class, 'store'])->name('party-invoice-import.store');
        Route::get('party-invoice-import/{partyInvoiceImportBatch}', [PartyInvoiceImportController::class, 'show'])->name('party-invoice-import.show');
        Route::patch('party-invoice-import/{partyInvoiceImportBatch}', [PartyInvoiceImportController::class, 'update'])->name('party-invoice-import.update');
        Route::post('party-invoice-import/{partyInvoiceImportBatch}/approve', [PartyInvoiceImportController::class, 'approve'])->name('party-invoice-import.approve');
        Route::post('party-invoice-import/{partyInvoiceImportBatch}/reject', [PartyInvoiceImportController::class, 'reject'])->name('party-invoice-import.reject');
        Route::put('agent/{id}/toggle-status', [AgentController::class, 'toggleStatus'])->name('agent.toggleStatus');
        Route::put('vendor/logs/{log}/restore', [VendorController::class, 'restore'])->name('vendor.restore');
        Route::put('vendor/{id}/toggle-status', [VendorController::class, 'toggleStatus'])->name('vendor.toggleStatus');
        Route::put('customer/logs/{log}/restore', [CustomerRoleController::class, 'restore'])->name('customer.restore');
        Route::put('customer/{id}/toggle-status', [CustomerRoleController::class, 'toggleStatus'])->name('customer.toggleStatus');
        Route::resource('portal-management', PortalManagementController::class);
        Route::post('/portal-management/update/balance', [PortalManagementController::class, 'updateBalance'])->name('portal-management.update.balance');
        Route::post('/portal-management/update/payment-info', [PortalManagementController::class, 'updatePaymentInfo'])->name('portal-management.update.payment-info');
        Route::get('/portal-management/{id}/journal', [PortalManagementController::class, 'journal'])->name('portal-management.journal');
        Route::get('banks/dashboard', [BankControllerV2::class, 'dashboard'])->name('banks.dashboard');
        Route::get('banks/payment-entry', [BankControllerV2::class, 'paymentEntry'])
            ->middleware('permission:view payment entry|view all payment entry')
            ->name('banks.payment-entry');
        // Same figures as the page, as JSON — lets a cash entry refresh the
        // dashboard in place instead of reloading it. See dashboardData().
        Route::get('banks/dashboard/data', [BankControllerV2::class, 'dashboardData'])->name('banks.dashboard.data');
        // Printable sheet of the Recent Transactions table — a period, a day,
        // or the ticked rows. See printRecentTransactions().
        Route::get('banks/dashboard/print', [BankControllerV2::class, 'printRecentTransactions'])->name('banks.dashboard.print');
        Route::get('banks/search', [BankControllerV2::class, 'search'])->name('banks.search');
        Route::get('banks/load', [BankControllerV2::class, 'load'])->name('banks.load');
        Route::resource('banks', BankControllerV2::class);
        Route::get('banks/{bank}/statement', [BankControllerV2::class, 'statement'])->name('banks.statement');
        Route::get('banks/{bank}/statement/pdf', [BankControllerV2::class, 'printStatement'])->name('banks.statement.pdf');
        // Daily reconciliation checkpoints — the real bank statement's closing
        // balance per day, compared live against the ledger. See reconciliation().
        Route::get('banks/{bank}/reconciliation', [BankControllerV2::class, 'reconciliation'])->name('banks.reconciliation');
        Route::post('banks/{bank}/reconciliation', [BankControllerV2::class, 'storeReconciliation'])->name('banks.reconciliation.store');
        // Side-by-side comparison of the bank's own PDF statement (read in
        // the browser, posted as text) with the ledger. See compareStatement().
        Route::post('banks/{bank}/reconciliation/compare', [BankControllerV2::class, 'compareStatement'])->name('banks.reconciliation.compare');
        Route::delete('banks/{bank}/reconciliation/{reconciliation}', [BankControllerV2::class, 'destroyReconciliation'])->name('banks.reconciliation.destroy');
        Route::post('banks/{bank}/transaction', [BankControllerV2::class, 'recordTransaction'])->name('banks.transaction');
        Route::post('banks/journal/{journalEntry}/reverse', [BankControllerV2::class, 'reverseTransaction'])->name('banks.journal.reverse');
        // Text corrections and audited amount/charge/VAT corrections for active
        // manual bank entries. Allocated invoice payments still require reversal.
        Route::post('banks/journal/{journalEntry}/remarks', [BankControllerV2::class, 'updateRemarks'])->name('banks.journal.remarks');
        Route::get('banks/journal/{journalEntry}/correction', [BankControllerV2::class, 'transactionCorrectionDetails'])->name('banks.journal.correction-details');
        Route::post('banks/journal/{journalEntry}/transfer-correction', [BankControllerV2::class, 'correctBankTransfer'])->name('banks.journal.transfer-correction');
        Route::post('banks/journal/{journalEntry}/salary-bank-charge', [BankControllerV2::class, 'correctSalaryBankCharge'])->name('banks.journal.salary-bank-charge');
        Route::post('banks/journal/{journalEntry}/owner-drawing-correction', [BankControllerV2::class, 'correctOwnerDrawing'])->name('banks.journal.owner-drawing-correction');
        Route::get('banks/journal/{journalEntry}/amount', [BankControllerV2::class, 'transactionAmountDetails'])->name('banks.journal.amount-details');
        Route::post('banks/journal/{journalEntry}/amount', [BankControllerV2::class, 'updateTransactionAmount'])->name('banks.journal.amount');
        // Date correction, with an audit trail — see updateJournalDate().
        Route::post('banks/journal/{journalEntry}/date', [BankControllerV2::class, 'updateJournalDate'])->name('banks.journal.date');

        // ── Capital & Financing — the loan book ──────────────────────────────
        // Three books on one desk: lent out, borrowed, and the employee book
        // mirrored read-only from Payroll. Writes no journal entries by the owner's
        // rule; see the create_financing_tables migration.
        Route::get('financing', [FinancingController::class, 'index'])->name('financing.index');
        Route::post('financing', [FinancingController::class, 'store'])->name('financing.store');
        // Capital movements — owner money in and out. Declared BEFORE the
        // {financing} wildcard so 'capital' is never swallowed as a loan id.
        Route::post('financing/capital', [FinancingController::class, 'storeCapital'])->name('financing.capital.store');
        Route::get('financing/capital/print', [FinancingController::class, 'printCapital'])->name('financing.capital.print');
        Route::post('financing/capital/send', [FinancingController::class, 'sendCapitalStatement'])->name('financing.capital.send');
        Route::delete('financing/capital/{capital}', [FinancingController::class, 'destroyCapital'])->name('financing.capital.destroy');
        // Categories and sub-categories — one self-parenting table, so these three
        // cover both levels. Also before the {financing} wildcard.
        Route::post('financing/categories', [FinancingController::class, 'storeCategory'])->name('financing.categories.store');
        Route::put('financing/categories/{category}', [FinancingController::class, 'updateCategory'])->name('financing.categories.update');
        Route::delete('financing/categories/{category}', [FinancingController::class, 'destroyCategory'])->name('financing.categories.destroy');
        // Inter-company liabilities — one company's expense paid from another's bank
        // account. Tracking only, posts nothing. Before the {financing} wildcard for
        // the same reason 'capital' and 'categories' are: 'liability' must never be
        // read as a loan id.
        Route::post('financing/liability/{liability}/settle', [FinancingController::class, 'settleLiability'])->name('financing.liability.settle');
        Route::get('financing/liability/print', [FinancingController::class, 'printLiability'])->name('financing.liability.print');
        Route::get('financing/{financing}', [FinancingController::class, 'show'])->name('financing.show');
        Route::put('financing/{financing}', [FinancingController::class, 'update'])->name('financing.update');
        Route::post('financing/{financing}/regenerate', [FinancingController::class, 'regenerate'])->name('financing.regenerate');
        Route::post('financing/{financing}/payment', [FinancingController::class, 'recordPayment'])->name('financing.payment');
        // A balance brought into the ERP from an older running loan. No cash
        // moves today: Dr Opening Balance Equity / Cr the loan liability.
        Route::post('financing/{financing}/opening-balance', [FinancingController::class, 'recordOpeningBalance'])->name('financing.opening-balance');
        // Money going OUT on a running account — the counterpart to payment, which
        // brings it back. Only a running account accepts one; the controller refuses
        // it on a fixed loan, whose principal is handed over once at setup.
        Route::post('financing/{financing}/drawdown', [FinancingController::class, 'recordDrawdown'])->name('financing.drawdown');
        Route::delete('financing/{financing}', [FinancingController::class, 'destroy'])->name('financing.destroy');

        Route::resource('ticket-direct-sale', TicketDirectSaleController::class)
            ->only(['index', 'create', 'store', 'edit'])
            ->names('ticket-direct-sale');
        // Ticket Services — Refund / Re-Issue / Void / EMD, the same forms as the entry page's tabs
        Route::get('ticket-services', fn (string $role) => redirect()->route('role.ticket-direct-sale.create', ['role' => $role, 'ops' => 1, 'tab' => request('tab', 'refund')]))->name('ticket-services');
        Route::resource('ticket-bookings', TicketBookingController::class);
        Route::post('ticket-bookings/{ticket_booking}/status', [TicketBookingController::class, 'changeStatus'])->name('ticket-bookings.status');
        Route::post('ticket-bookings/{ticket_booking}/resend', [TicketBookingController::class, 'resend'])->name('ticket-bookings.resend');
        Route::get('ticket-direct-sale/{ticket_direct_sale}', [TicketPurchaseController::class, 'show'])->name('ticket-direct-sale.show');
        Route::match(['put', 'patch'], 'ticket-direct-sale/{ticket_direct_sale}', [TicketPurchaseController::class, 'update'])->name('ticket-direct-sale.update');
        Route::delete('ticket-direct-sale/{ticket_direct_sale}', [TicketPurchaseController::class, 'destroy'])->name('ticket-direct-sale.destroy');
        Route::get('ticket-invoice-lookup', [TicketDirectSaleController::class, 'invoiceLookup'])->name('ticket-invoice-lookup');
        Route::post('ticket-quick-create-route', [TicketDirectSaleController::class, 'quickCreateTicket'])->name('ticket-quick-create-route');
        // Ticket Entry: a new passenger (passport holder "+") becomes a client in Sold to — 2026-10-25
        Route::post('ticket-quick-create-client', [TicketDirectSaleController::class, 'quickCreateClient'])->name('ticket-quick-create-client');
        // Ticket Entry → Import PDF: reads ticket PDFs (text from pdf.js) into the entry cards. Writes nothing.
        Route::post('ticket-import/read', [TicketImportController::class, 'read'])->name('ticket-import.read');
        // Ticket Import → CSV / Excel: one row per passenger ticket, into the same cards. Writes nothing.
        Route::post('ticket-import/sheet', [TicketImportController::class, 'sheet'])->name('ticket-import.sheet');
        Route::get('ticket-import/template', [TicketImportController::class, 'template'])->name('ticket-import.template');
        Route::get('ticket-import/conflict', [TicketImportController::class, 'conflict'])->name('ticket-import.conflict');
        Route::post('ticket-import/resolve', [TicketImportController::class, 'resolve'])->name('ticket-import.resolve');
        // after a Save: remember the labels of what staff typed that the PDF did not give (labels only)
        Route::post('ticket-import/learn', [TicketImportController::class, 'learn'])->name('ticket-import.learn');
        Route::post('ticket-refund', [TicketDirectSaleController::class, 'storeRefund'])->name('ticket-refund.store');
        Route::post('ticket-reissue', [TicketDirectSaleController::class, 'storeReissue'])->name('ticket-reissue.store');

        Route::group(['prefix' => 'vouchers', 'as' => 'vouchers.'], function () {
            Route::get('/', [VoucherController::class, 'index'])->name('index');
            Route::post('/store', [VoucherController::class, 'store'])->name('store');
            Route::post('/update', [VoucherController::class, 'update'])->name('update'); // This was the missing link
            Route::delete('/destroy', [VoucherController::class, 'destroy'])->name('destroy');
        });
        Route::resource('accounts', AccountController::class);
        Route::resource('journals', JournalController::class);
        Route::post('journals/{journal}/reverse-replace', [JournalController::class, 'reverseReplace'])->name('journals.reverse-replace');
        Route::post('journals/{journal}/items/{item}/reclassify', [JournalController::class, 'reclassify'])
            ->name('journals.items.reclassify');
        Route::get('journals/{journal}/voucher', [JournalController::class, 'voucher'])->name('journals.voucher');
        Route::get('journals/{journal}/party-voucher', [JournalController::class, 'partyVoucher'])->name('journals.party-voucher');
        Route::resource('bank_transfers', BankTransferController::class);
        Route::post('bank_transfers/{bank_transfer}/reverse', [BankTransferController::class, 'reverse'])->name('bank_transfers.reverse');
        Route::resource('payrol', PayrolController::class);
        Route::resource('route', RouteManagementController::class);
        Route::resource('airlines', AirlineController::class)->except('show');
        Route::resource('tickets', TicketController::class);
        Route::get('get-ticket-edit-modal', [TicketController::class, 'getTicketEditModal'])->name('get-ticket-edit-modal');
        Route::get('ticket-sales/available-purchases', [TicketSaleController::class, 'availablePurchases'])->name('ticket-sales.available-purchases');
        Route::get('ticket-sales/{ticket_sale}/detail', [TicketSaleController::class, 'detail'])->name('ticket-sales.detail');
        Route::resource('ticket-sales', TicketSaleController::class);
        Route::post('ticket-sales/make/payment/{id}', [TicketSaleController::class, 'make_payment'])->name('ticket-sales.make.payment');
        // Ticket Purchase and the former Direct Ticket module are one screen
        // over the same ticket_purchases table. Keep both route-name families
        // alive for bookmarks, Operation Tracker and Contract Flight callers.
        Route::get('ticket-purchase', [TicketDirectSaleController::class, 'index'])->name('ticket-purchase.index');
        Route::resource('ticket-purchase', TicketPurchaseController::class)->except('index');
        Route::post('ticket-purchase/make/payment/{id}', [TicketPurchaseController::class, 'make_payment'])->name('ticket-purchase.make.payment');
        Route::get('ticket-purchase/{id}/legs', [TicketPurchaseController::class, 'legs'])->name('ticket-purchase.legs');
        Route::post('ticket-purchase/{id}/legs', [TicketPurchaseController::class, 'updateLegs'])->name('ticket-purchase.legs.update');
        // Ticket Orders → Change (2026-10-06): re-issue / reschedule / refund / void
        // one ticket; a contract-flight ticket goes through the flight's own code.
        Route::get('ticket-changes/{ticketPurchase}', [TicketChangeController::class, 'context'])->whereNumber('ticketPurchase')->name('ticket-changes.context');
        Route::post('ticket-changes/{ticketPurchase}', [TicketChangeController::class, 'store'])->whereNumber('ticketPurchase')->name('ticket-changes.store');
        Route::post('ticket-changes/{ticketPurchase}/correct-refund', [TicketChangeController::class, 'correctRefund'])->whereNumber('ticketPurchase')->name('ticket-changes.correct-refund');
        // Cancel return's window + Family tick (2026-10-14)
        Route::post('ticket-changes/{ticketPurchase}/return-window', [TicketChangeController::class, 'returnWindow'])->whereNumber('ticketPurchase')->name('ticket-changes.return-window');
        // Ticket Orders -> Delete wrong entry (2026-10-12): the ticket(s), their invoice, changes and every posting. Super admin only.
        Route::get('ticket-purge/{ticketPurchase}', [TicketPurgeController::class, 'preview'])->whereNumber('ticketPurchase')->name('ticket-purge.preview');
        Route::post('ticket-purge/{ticketPurchase}', [TicketPurgeController::class, 'purge'])->whereNumber('ticketPurchase')->name('ticket-purge.store');
        // Change → All Trails and Edit (2026-10-26): history, every posting, and the correction that replaces them. Super admin only.
        Route::get('ticket-trail/{ticketPurchase}', [TicketTrailController::class, 'show'])->whereNumber('ticketPurchase')->name('ticket-trail.show');
        Route::post('ticket-trail/{ticketPurchase}/preview', [TicketTrailController::class, 'preview'])->whereNumber('ticketPurchase')->name('ticket-trail.preview');
        Route::post('ticket-trail/{ticketPurchase}', [TicketTrailController::class, 'apply'])->whereNumber('ticketPurchase')->name('ticket-trail.apply');
        // Ticket Entry: a passenger's refunded tickets awaiting their new ticket (2026-10-07)
        Route::get('ticket-changes/refunded/{holder}', [TicketChangeController::class, 'refundedFor'])->whereNumber('holder')->name('ticket-changes.refunded');
        // Ticket Dashboard (2026-10-04) — read-only; the Ticketing band's first tab.
        Route::get('ticketing/dashboard', [TicketDashboardController::class, 'index'])->name('ticketing.dashboard');
        Route::get('ticketing/accounts', [TicketAccountsController::class, 'index'])->name('ticketing.accounts');
        // the Ticketing workbook (.xlsx) — built from the database each time it is opened
        Route::get('ticketing/accounts/workbook', [TicketAccountsController::class, 'workbook'])->name('ticketing.accounts.workbook');
        Route::get('ticketing/accounts/ledger/{ledger}', [TicketAccountsController::class, 'ledger'])->whereIn('ledger', ['agents', 'vendors'])->name('ticketing.accounts.ledger');

        Route::post('payment-schedules/adhoc', [PaymentScheduleController::class, 'storeAdHoc'])->name('payment-schedules.adhoc');
        Route::get('headline-notices', [HeadlineNoticeController::class, 'index'])->name('headline-notices.index');
        // Per-user pinned pages (right-click a sidebar link). See layout/shortcuts.blade.php.
        Route::post('shortcuts', [UserShortcutController::class, 'store'])->name('shortcuts.store');
        Route::delete('shortcuts/{shortcut}', [UserShortcutController::class, 'destroy'])->whereNumber('shortcut')->name('shortcuts.destroy');
        Route::get('payment-schedules/parties-by-type', [PaymentScheduleController::class, 'partiesByType'])->name('payment-schedules.parties-by-type');
        Route::get('payment-schedules/party-types-by-company', [PaymentScheduleController::class, 'partyTypesByCompany'])->name('payment-schedules.party-types-by-company');
        Route::get('payment-schedules/project-categories-by-company', [PaymentScheduleController::class, 'projectCategoriesByCompany'])->name('payment-schedules.project-categories-by-company');
        Route::resource('payment-schedules', PaymentScheduleController::class)->only(['index', 'store', 'destroy']);
        Route::patch('payment-schedules/{schedule}/cancel', [PaymentScheduleController::class, 'cancel'])->name('payment-schedules.cancel');
        Route::patch('payment-schedules/{schedule}/reopen', [PaymentScheduleController::class, 'reopen'])->name('payment-schedules.reopen');
        Route::patch('payment-schedules/{schedule}/approve', [PaymentScheduleController::class, 'approve'])->name('payment-schedules.approve');
        Route::patch('payment-schedules/{schedule}/reject', [PaymentScheduleController::class, 'reject'])->name('payment-schedules.reject');
        Route::patch('payment-schedules/{schedule}/reschedule', [PaymentScheduleController::class, 'reschedule'])->name('payment-schedules.reschedule');
        Route::patch('payment-schedules/{schedule}/mark-paid', [PaymentScheduleController::class, 'markPaid'])->name('payment-schedules.mark-paid');
        Route::get('payment-schedules/{schedule}/voucher', [PaymentScheduleController::class, 'voucher'])->name('payment-schedules.voucher');
        Route::patch('payment-schedules/{schedule}/priority', [PaymentScheduleController::class, 'setPriority'])->name('payment-schedules.priority');

        Route::resource('sms-marketing', SmsMarketingController::class);
        Route::resource('email-marketing', EmailMarketingController::class);
        Route::resource('whatsapp-marketing', WhatsappMarketingController::class);

        Route::get('site-settings', [SiteSettingController::class, 'index'])->name('site-settings.index');
        Route::put('site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');

        Route::get('demo-data', [DemoDataController::class, 'index'])->name('demo-data.index');
        Route::delete('demo-data', [DemoDataController::class, 'destroy'])->name('demo-data.destroy');

        Route::resource('countries', CountryController::class)->except('show');
        Route::resource('states', StateController::class)->except('show');
        Route::resource('airport', AirportController::class)->except('show');
        Route::get('get-states-by-country', [StateController::class, 'getStatesByCountry'])->name('get-states-by-country');
        Route::resource('company-settings', CompanyController::class)->except('show');
        Route::get('company/{company}/dashboard', [CompanyDashboardController::class, 'show'])->name('company.dashboard');
        // Wood Art Interiors routes live in the module: Modules/WoodArt/routes/web.php
        Route::resource('sms_templates', SmsTemplateController::class)->except('show');
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('designations', DesignationController::class)->except('show');
        Route::resource('shifts', ShiftController::class)->except('show');
        Route::resource('holidays', HolidayController::class)->except('show');
        Route::resource('attendances', AttendanceController::class)->except('show');
        Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
        Route::resource('leaves', LeaveController::class);
        Route::resource('leave-types', LeaveTypeController::class)->except('show');
        Route::resource('attendence-settings', AttendanceSettingController::class)->except('show');
        Route::resource('salary-templates', SalaryTemplateController::class)->except('show');

        Route::get('/salary-template/{id}', [SalaryTemplateController::class, 'getSalary'])
            ->name('salary.template.get');
        Route::resource('sales-records', SaleRecordController::class)->except('show');

        Route::get('get-employee-salary', [EmployeeSalaryController::class, 'getEmployeeSalary'])->name('get-employee-salary');
        Route::get('employee-salaries/attendance', [EmployeeSalaryController::class, 'getAttendanceData'])
            ->name('employee-salaries.attendance');
        Route::get('employee-salaries/paid-due-report', [EmployeeSalaryController::class, 'paidDueReport'])
            ->name('employee-salaries.paid-due-report');
        Route::get('employee-salaries/paid-due-report/print', [EmployeeSalaryController::class, 'paidDueReportPrint'])
            ->name('employee-salaries.paid-due-report.print');
        Route::get('employee-salaries/{id}/{action}', [EmployeeSalaryController::class, 'handleAction'])
            ->name('employee-salaries.action');
        Route::resource('employee-salaries', EmployeeSalaryController::class);
        // Recording a repayment sits outside the resource: it is a movement on a
        // loan, not an edit of one, and it is gated on 'create loan' rather than
        // 'edit loan' — collecting money is not the same right as rewriting terms.
        Route::post('loans/repay', [LoanController::class, 'repay'])->name('loans.repay');
        // Static export paths must stay above the resource, or `loans/export` would
        // be swallowed by `loans/{loan}` and try to open a loan called "export".
        Route::get('loans/export/excel', [LoanController::class, 'exportExcel'])->name('loans.export.excel');
        Route::get('loans/export/pdf', [LoanController::class, 'exportPdf'])->name('loans.export.pdf');
        Route::get('loans/{loan}/statement', [LoanController::class, 'statement'])->name('loans.statement');
        Route::resource('loans', LoanController::class);
        Route::resource('payments', PaymentController::class)->except('show');
        // Static paths above the resource, or `payslips/export` is swallowed by
        // `payslips/{payslip}` and treated as a payslip called "export".
        Route::get('payslips/export/excel', [PayslipController::class, 'exportExcel'])->name('payslips.export.excel');
        Route::get('payslips/export/pdf', [PayslipController::class, 'exportPdf'])->name('payslips.export.pdf');
        Route::get('payslips/statement', [PayslipController::class, 'statementLookup'])->name('payslips.statement.lookup');
        Route::get('payslips/{payslip}/statement', [PayslipController::class, 'statement'])->name('payslips.statement');
        Route::resource('payslips', PayslipController::class)->except('show');
        // Static export paths above the resource, or `advance-salaries/export` is
        // swallowed by `advance-salaries/{advance_salary}`.
        Route::get('advance-salaries/export/excel', [AdvanceSalaryController::class, 'exportExcel'])->name('advance-salaries.export.excel');
        Route::get('advance-salaries/export/pdf', [AdvanceSalaryController::class, 'exportPdf'])->name('advance-salaries.export.pdf');
        Route::resource('advance-salaries', AdvanceSalaryController::class);
        Route::post('advance-salaries/{id}/release', [AdvanceSalaryController::class, 'release'])->name('advance-salaries.release');
        Route::post('advance-salaries/{id}/reverse', [AdvanceSalaryController::class, 'reverse'])->name('advance-salaries.reverse');

        // Employee Request & Requirement System — Phase 1 + Phase 2 + Phase 3
        // Static routes BEFORE resource (avoids {id} wildcard capture)
        Route::get('employee-requests/self-service', [EmployeeRequestController::class, 'selfService'])->name('employee-requests.self-service');
        Route::get('employee-requests/report', [EmployeeRequestController::class, 'report'])->name('employee-requests.report');
        Route::resource('employee-requests', EmployeeRequestController::class)->except('show');
        Route::post('employee-requests/{id}/approve', [EmployeeRequestController::class, 'approve'])->name('employee-requests.approve');
        Route::post('employee-requests/{id}/reject', [EmployeeRequestController::class, 'reject'])->name('employee-requests.reject');
        Route::post('employee-requests/{id}/fulfill', [EmployeeRequestController::class, 'fulfill'])->name('employee-requests.fulfill');
        Route::post('employee-requests/{id}/disburse', [EmployeeRequestController::class, 'disburse'])->name('employee-requests.disburse');
        Route::post('employee-requests/{id}/recover', [EmployeeRequestController::class, 'recover'])->name('employee-requests.recover');
        // Phase 3
        Route::post('employee-requests/{id}/fast-track', [EmployeeRequestController::class, 'fastTrack'])->name('employee-requests.fast-track');
        Route::post('employee-requests/{id}/close', [EmployeeRequestController::class, 'close'])->name('employee-requests.close');
        Route::get('employee-requests/{id}/noc-pdf', [EmployeeRequestController::class, 'generateNoc'])->name('employee-requests.noc-pdf');
        Route::get('require-assignments/overdue', [RequireAssignmentController::class, 'overdueList'])->name('require-assignments.overdue');
        Route::put('require-assignments/{id}/verify', [RequireAssignmentController::class, 'verify'])->name('require-assignments.verify');
        Route::put('require-assignments/{id}/escalate', [RequireAssignmentController::class, 'escalate'])->name('require-assignments.escalate');
        Route::get('advance-salaries/{id}/payment-slip', [AdvanceSalaryController::class, 'paymentSlip'])
            ->name('advance-salaries.payment-slip');
        Route::get('advance-salaries/{id}/payment-slip/download', [AdvanceSalaryController::class, 'downloadPaymentSlip'])
            ->name('advance-salaries.payment-slip.download');
        Route::get('advance-salaries/{id}/schedule', [AdvanceSalaryController::class, 'schedule'])
            ->name('advance-salaries.schedule');
        Route::post('advance-salaries/{id}/schedule/pay', [AdvanceSalaryController::class, 'schedulePay'])
            ->name('advance-salaries.schedule.pay');
        Route::resource('commissions', CommissionController::class)->except('show');
        // Department, Category and Sub-category are one chain, so they are set up on
        // one screen. The three per-entity resources below still own every write.
        Route::get('expense-classification', [ExpenseClassificationController::class, 'index'])
            ->name('expense-classification.index');

        // The old per-entity list URLs still resolve — bookmarks, and the two
        // non-AJAX redirect()->route() fallbacks inside the category and
        // sub-category controllers, both land here. Declared before the resources so
        // they win the GET; the resources keep store/update/destroy. A controller
        // action rather than Route::redirect() because the destination has to carry
        // the {role} prefix, which a static destination string cannot.
        foreach (['expense-departments', 'expense-categories', 'expense-subcategories'] as $legacyExpenseList) {
            Route::get($legacyExpenseList, [ExpenseClassificationController::class, 'redirectLegacy'])
                ->name($legacyExpenseList.'.index');
        }

        // The expense desk's own departments — separate from HR's, and company-scoped
        // so the expense form can fill the company in from one.
        Route::resource('expense-departments', ExpenseDepartmentController::class)
            ->only(['store', 'update', 'destroy']);
        Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show', 'index']);
        Route::resource('party-types', PartyTypeController::class)->except(['show', 'create', 'edit']);
        Route::resource('expense-subcategories', ExpenseSubCategoryController::class)->except(['show', 'index']);
        Route::get('get-expense-sub-category', [ExpenseSubCategoryController::class, 'getExpenseSubCategory'])->name('get-expense-sub-category');

        // Petty cash — money handed to a custodian and not yet spent. Issue and
        // return are their own actions rather than a generic update because each one
        // writes a journal entry; editing a row would not.
        Route::get('petty-cash', [PettyCashController::class, 'index'])->name('petty-cash.index');

        // The daily cost fund — how much a company MAY spend in cash today. Declared
        // before the {float} routes so a literal path can never be read as a float id,
        // and kept on the petty cash desk rather than inside it because it moves no
        // money: it writes no journal entry at all, it only sets a ceiling.
        Route::post('petty-cash/daily-fund', [PettyCashController::class, 'saveDailyFunds'])
            ->name('petty-cash.daily-fund.save');
        Route::get('petty-cash/daily-fund/{company}/breakdown', [PettyCashController::class, 'dailyFundBreakdown'])
            ->name('petty-cash.daily-fund.breakdown');

        Route::post('petty-cash', [PettyCashController::class, 'store'])->name('petty-cash.store');
        // Declared ABOVE petty-cash/{float}, or "report" would be read as a float id.
        Route::get('petty-cash/report', [PettyCashController::class, 'report'])->name('petty-cash.report');
        Route::get('petty-cash/report/print', [PettyCashController::class, 'reportPrint'])->name('petty-cash.report.print');

        Route::get('petty-cash/{float}/statement', [PettyCashController::class, 'statement'])->name('petty-cash.statement');
        // "print" in the path is load-bearing, same as the expense report's export:
        // layout/app.blade.php's speculation rules skip these, so hovering the link
        // never makes the server build a statement nobody asked for.
        Route::get('petty-cash/{float}/statement/print', [PettyCashController::class, 'printStatement'])->name('petty-cash.statement.print');
        Route::put('petty-cash/{float}', [PettyCashController::class, 'update'])->name('petty-cash.update');
        Route::post('petty-cash/{float}/issue', [PettyCashController::class, 'issue'])->name('petty-cash.issue');
        Route::post('petty-cash/{float}/return', [PettyCashController::class, 'returnCash'])->name('petty-cash.return');
        Route::delete('petty-cash/{float}', [PettyCashController::class, 'destroy'])->name('petty-cash.destroy');

        // Paying staff back for what they spent themselves. Its own path rather than
        // under expenses/ so it cannot be caught by `expenses/{expense}` below, and
        // because a payment is not an expense — it settles one.
        Route::get('employee-reimbursements', [EmployeeReimbursementController::class, 'index'])->name('employee-reimbursements.index');
        Route::post('employee-reimbursements', [EmployeeReimbursementController::class, 'store'])->name('employee-reimbursements.store');
        Route::post('employee-reimbursements/{reimbursement}/reverse', [EmployeeReimbursementController::class, 'reverse'])->name('employee-reimbursements.reverse');

        Route::get('expenses/budget-setup', [ExpenseBudgetController::class, 'index'])->name('expenses.budget-setup');
        Route::post('expenses/budget-setup', [ExpenseBudgetController::class, 'store'])->name('expenses.budget-setup.store');
        Route::put('expenses/budget-setup/{budget}', [ExpenseBudgetController::class, 'update'])->name('expenses.budget-setup.update');
        Route::delete('expenses/budget-setup/{budget}', [ExpenseBudgetController::class, 'destroy'])->name('expenses.budget-setup.destroy');
        Route::get('expenses/report', [ExpenseController::class, 'report'])->name('expenses.report');
        // "export" in the path is load-bearing: layout/app.blade.php's speculation
        // rules exclude /*export* from hover-prefetch, so pointing at this link never
        // makes the server build a CSV nobody asked for.
        Route::get('expenses/report/export', [ExpenseController::class, 'reportExport'])->name('expenses.report.export');
        Route::get('expenses/report/print', [ExpenseController::class, 'reportPrint'])->name('expenses.report.print');

        Route::get('expenses/recurring', [RecurringExpenseController::class, 'index'])->name('expenses.recurring.index');
        Route::post('expenses/recurring', [RecurringExpenseController::class, 'store'])->name('expenses.recurring.store');
        Route::put('expenses/recurring/{recurringExpense}', [RecurringExpenseController::class, 'update'])->name('expenses.recurring.update');
        Route::delete('expenses/recurring/{recurringExpense}', [RecurringExpenseController::class, 'destroy'])->name('expenses.recurring.destroy');

        // Subscriptions, EMI & Renewals — the screen only, no data layer yet.
        // Declared HERE, above Route::resource('expenses'), for the same reason
        // budget-setup and report are: `expenses/{expense}` would otherwise match
        // `expenses/subscriptions` first and hand "subscriptions" over as an id.
        Route::get('expenses/subscriptions', [ExpenseSubscriptionController::class, 'index'])
            ->name('expenses.subscriptions');
        // Retry a renewal DM did not accept. Separate from approving the expense,
        // because the expense already succeeded — only the push to DM failed.
        Route::post('expenses/subscriptions/{payment}/retry-dm', [ExpenseSubscriptionController::class, 'retryDmSync'])
            ->name('expenses.subscriptions.retry-dm');

        // Read by the expense form's cash-limit meter whenever the date changes.
        Route::get('expenses/daily-fund', [ExpenseController::class, 'dailyFund'])->name('expenses.daily-fund');
        Route::get('expenses/print', [ExpenseController::class, 'printList'])->name('expenses.print');
        // Compatibility for stale links/bookmarks from the retired batch form.
        // Expense creation is again handled by the modal on the index page.
        Route::get('expenses/add', fn (string $role) => redirect()->route('role.expenses.index', ['role' => $role]));
        Route::get('expenses/{id}/slip', [ExpenseController::class, 'printSlip'])->name('expenses.slip');
        Route::get('expenses/{id}/items', [ExpenseController::class, 'getItems'])->name('expenses.items');
        // Approve and reverse are their own actions rather than a generic update:
        // each one writes to the ledger, and each is gated on `approve expense`
        // where editing the expense itself is not. Declared before the resource so
        // they win the POST.
        Route::post('expenses/{id}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('expenses/{id}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');
        Route::resource('expenses', ExpenseController::class)->except('show');
        Route::resource('units', UnitController::class)->except('show');
        Route::resource('brands', BrandController::class)->except('show');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('sub-categories', SubCategoryController::class)->except('show');
        Route::get('get-sub-category', [SubCategoryController::class, 'getSubCategory'])->name('get-sub-category');
        Route::resource('branches', BranchController::class)->except('show');
        Route::resource('customers', CustomerController::class)->except('show');
        Route::resource('suppliers', SupplierController::class)->except('show');
        Route::resource('products', ProductController::class)->except('show');
        Route::get('get-product-edit-modal', [ProductController::class, 'getProductEditModal'])->name('get-product-edit-modal');
        Route::resource('sales', SaleController::class)->except('show');
        Route::post('sales/make/payment/{id}', [SaleController::class, 'make_payment'])->name('sales.make.payment');
        Route::get('sales/invoice/{id}', [SaleController::class, 'invoice'])->name('sales.view-invoice');
        Route::get('sales/view/modal/{id}', [SaleController::class, 'modal'])->name('sales.view.modal');
        Route::resource('purchases', PurchaseController::class)->except('show');
        Route::post('purchases/make/payment/{id}', [PurchaseController::class, 'make_payment'])->name('purchases.make.payment');
        Route::get('purchases/invoice/{id}', [PurchaseController::class, 'invoice'])->name('purchases.view-invoice');
        Route::resource('stock-transfers', StockTransferController::class)->except('show');
        Route::resource('stock-movements', StockMovementController::class)->except('show');
        Route::resource('return-refs', SaleReturnController::class)->except('show');
        Route::resource('device-settings', DeviceController::class)->except('show');
        Route::get('resignations/print', [ResignationController::class, 'printList'])->name('resignations.print');
        Route::resource('resignations', ResignationController::class);
        Route::post('resignations/{id}/approve', [ResignationController::class, 'approve'])->name('resignations.approve');
        Route::post('resignations/{id}/reject', [ResignationController::class, 'reject'])->name('resignations.reject');
        Route::delete('resignations/{resignation}/attachment/{attachment}', [ResignationController::class, 'deleteAttachment'])->name('resignations.attachment.delete');

        // role
        Route::resource('role-permission', RoleController::class);

        // INVOICE SETTINGS ROUTE
        Route::resource('invoice-templates', InvoiceTemplateController::class);
        Route::post('invoice-templates/{template}/duplicate', [InvoiceTemplateController::class, 'duplicate'])->name('invoice-templates.duplicate');
        Route::get('invoice-templates/{template}/preview', [InvoiceTemplateController::class, 'preview'])->name('invoice-templates.preview');

        // CRM modules
        Route::get('crm/dashboard', [CrmDashboardController::class, 'index'])->name('crm.dashboard');
        Route::resource('lead-manager', LeadManagerController::class)->except('create', 'show', 'edit');
        Route::get('lead-manager/{lead}/status-history', [LeadManagerController::class, 'getStatusHistory'])
            ->name('lead-manager.status-history');
        Route::get('lead-manager/{lead}/visa-documents', [LeadManagerController::class, 'getVisaDocuments'])
            ->name('lead-manager.visa-documents');
        Route::patch('lead-manager/visa-document/{doc}/toggle', [LeadManagerController::class, 'toggleVisaDocument'])
            ->name('lead-manager.visa-document.toggle');
        Route::patch('lead-manager/{lead}/site-visit-done', [LeadManagerController::class, 'markSiteVisitDone'])
            ->name('lead-manager.site-visit-done');
        Route::post('lead-manager/{lead}/convert-to-project', [LeadManagerController::class, 'convertToProject'])
            ->name('lead-manager.convert-to-project');

        Route::resource('lead-source', LeadSourceController::class)->except('create', 'show', 'edit');
        Route::resource('lead-reminders', LeadReminderController::class)->except('create', 'show', 'edit');
        Route::resource('lead-followup', LeadFollowupController::class)->except('create', 'show', 'edit');
        Route::resource('project-categories', ProjectCategoryController::class)->except('create', 'show', 'edit');
        Route::resource('contract-types', ContractTypeController::class)->except('create', 'show', 'edit');
        Route::resource('deals', DealManageController::class)->except('create', 'show', 'edit');
        Route::resource('proposals', ProposalManageController::class)->except('create', 'show', 'edit');
        Route::resource('projects', ProjectManageController::class)->except('create', 'edit');
        Route::get('projects/ajax/department-types', [ProjectManageController::class, 'getProjectDepartments'])->name('projects.ajax.department-types');
        Route::get('projects/ajax/field-definitions', [ProjectManageController::class, 'getFieldDefinitions'])->name('projects.ajax.field-definitions');
        Route::resource('require-types', RequireTypeController::class)->except('create', 'edit', 'show');
        Route::get('require-types/{requireType}/fields', [RequireTypeController::class, 'getFields'])->name('require-types.fields');
        Route::post('require-types/{requireType}/fields', [RequireTypeController::class, 'storeField'])->name('require-types.fields.store');
        Route::put('project-field-definitions/{fieldDefinition}', [RequireTypeController::class, 'updateField'])->name('project-field-definitions.update');
        Route::delete('project-field-definitions/{fieldDefinition}', [RequireTypeController::class, 'destroyField'])->name('project-field-definitions.destroy');
        Route::resource('task-projects', TaskProjectController::class)->only('index');
        Route::resource('contracts', ContractManageController::class)->except('create', 'show', 'edit');
        Route::resource('ticket-departments', TicketDepartmentController::class)->except('create', 'show', 'edit');
        Route::resource('support-tickets', SupportTicketController::class)->except('create', 'edit');

        Route::post('support-tickets/{ticket}/replies', [SupportTicketController::class, 'storeReply'])->name('support-tickets.replies.store');

        // Office Todos
        Route::get('office-todos/{id}/edit', [OfficeTodoController::class, 'edit'])->name('office-todos.edit-data');
        Route::patch('office-todos/{id}/my-status', [OfficeTodoController::class, 'updateMyStatus'])->name('office-todos.my-status');
        Route::patch('office-todos/{id}/quick-status', [OfficeTodoController::class, 'quickStatus'])->name('office-todos.quick-status');
        Route::delete('office-todos/destroy', [OfficeTodoController::class, 'destroy'])->name('office-todos.destroy');
        Route::get('office-todos/{id}/checklists', [OfficeTodoController::class, 'getChecklists'])->name('office-todos.checklists');
        // Registered ahead of the {checklist} routes below: a literal segment must
        // win over the wildcard, or a PATCH to .../checklists/reorder is read as an
        // item id. The whereNumber() on those routes is the second line of defence.
        Route::patch('office-todos/{todo}/checklists/reorder', [OfficeTodoController::class, 'reorderChecklists'])->name('office-todos.checklist.reorder');
        Route::patch('office-todos/{todo}/checklists/{checklist}/toggle', [OfficeTodoController::class, 'toggleChecklist'])->whereNumber('checklist')->name('office-todos.checklist.toggle');
        Route::patch('office-todos/{todo}/checklists/{checklist}', [OfficeTodoController::class, 'updateChecklistItem'])->whereNumber('checklist')->name('office-todos.checklist.update');
        Route::delete('office-todos/{todo}/checklists/{checklist}', [OfficeTodoController::class, 'destroyChecklistItem'])->whereNumber('checklist')->name('office-todos.checklist.destroy');
        Route::post('office-todos/{todo}/checklists', [OfficeTodoController::class, 'storeChecklist'])->name('office-todos.checklist.store');
        Route::resource('office-todos', OfficeTodoController::class)->except(['create', 'show', 'destroy']);

        Route::put('support-ticket-replies/{reply}', [SupportTicketController::class, 'updateReply'])->name('support-tickets.replies.update');
        Route::delete('support-ticket-replies/{reply}', [SupportTicketController::class, 'destroyReply'])->name('support-tickets.replies.destroy');
        Route::get('support-tickets/attachments/{filename}', [SupportTicketController::class, 'downloadAttachment'])->name('support-tickets.attachment.download');

        Route::resource('estimates', EstimateController::class)->except(
            'create',
            'edit',
        );

        // Task modules
        Route::get('workspace-users/project/{project_id}', [
            WorkSpaceUserController::class,
            'getProjectUsers',
        ])->name('workspace-users.get-project-users');
        Route::resource(
            'workspace-users',
            WorkSpaceUserController::class,
        )->except('create', 'show', 'edit');
        Route::resource('boards', BoardController::class)->except(
            'create',
            'show',
        );
        Route::post('boards/update-status/{id}', [
            BoardController::class,
            'updateStatus',
        ])->name('boards.updateStatus');
        Route::resource('columns', ColumnController::class)->except(
            'create',
            'show',
            'edit',
        );
        Route::resource('labels', LabelController::class)->except(
            'create',
            'show',
            'edit',
        );
        // Task custom routes (must come before resource)
        Route::get('tasks', function () {
            $role = request()->route('role');
            $user = Auth::user();

            // Pick a specific board for super admin
            if ($role === 'super-admin' || $user->hasRole('super admin')) {

                $board = Board::query()->where('id', 24)->first();
                if (! $board) {
                    $board = Board::query()->first();
                }

            } else {
                $query = Board::query();

                $userId = (string) $user->id;
                $query->whereHas('project', function ($q) use ($userId) {
                    $q->whereJsonContains('team_members', $userId);
                });

                $boardId = Task::whereHas('users', fn ($q) => $q->where('users.id', $user->id))
                    ->whereNotNull('board_id')
                    ->latest('created_at')
                    ->value('board_id');

                $board = $boardId ? Board::find($boardId) : null;
            }

            if (! $board) {
                return redirect()->route('role.boards.index', $role)
                    ->with('error', 'No boards available. Please contact your administrator.');
            }

            return redirect()->route('role.tasks.board', ['role' => $role, 'board' => $board->id]);

        })->name('tasks.index');

        Route::get('board/{board}', [TaskController::class, 'index'])->name('tasks.board');
        Route::get('board/{board}/column/{column}/tasks', [TaskController::class, 'boardColumnTasks'])->whereNumber('column')->name('tasks.board.column');
        Route::post('tasks/{task}/copy', [TaskController::class, 'copy'])->name('tasks.copy');
        // Subtask search — must come before resource to avoid {task} conflict
        Route::get('tasks/search', [TaskController::class, 'searchTasks'])->name('tasks.search');

        // Single-task report (PDF) — the drawer's "Task report" button.
        // Before the resource for the same reason search is: custom first.
        Route::get('tasks/{task}/report', [TaskController::class, 'report'])->name('tasks.report');

        /* EMPLOYEE TASK OVERSIGHT — the super admin opens somebody else's
               board and acts on it.

               Above the resource because `tasks/{task}/oversight` has to be
               matched before `tasks/{task}` claims it, the same reason the
               search and report routes sit up here.

               Authorisation is NOT on these lines. The controller checks every
               entry point itself: a route is a door, and the powers behind this
               one are real enough that the lock belongs on the room. */
        Route::get('task-oversight', [EmployeeTaskOversightController::class, 'index'])->name('task-oversight.index');
        Route::get('task-oversight/{employee}', [EmployeeTaskOversightController::class, 'openEmployee'])->name('task-oversight.employee');
        Route::get('task-oversight/{employee}/board/{board}', [EmployeeTaskOversightController::class, 'board'])->name('task-oversight.board');
        Route::get('tasks/{task}/oversight', [EmployeeTaskOversightController::class, 'verdict'])->name('tasks.oversight.show');
        Route::post('tasks/{task}/oversight', [EmployeeTaskOversightController::class, 'saveVerdict'])->name('tasks.oversight.save');

        Route::resource('tasks', TaskController::class)->except('index', 'create', 'edit');

        // task move route required for drag-drop and list reordering
        Route::post('tasks/move', [TaskController::class, 'move'])->name('tasks.move');

        // Task attachment routes
        Route::post('tasks/attachments', [TaskController::class, 'storeAttachment'])->name('tasks.attachments');
        Route::get('/tasks/{task}/attachments', [TaskController::class, 'getAttachments'])->name('tasks.attachments.index');
        Route::delete('/tasks/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->name('tasks.attachments.destroy');

        // Task link routes
        Route::post('tasks/links', [TaskController::class, 'storeLink'])->name('tasks.links.store');
        Route::get('tasks/{task}/links', [TaskController::class, 'getLinks'])->name('tasks.links.index');
        Route::put('tasks/links/{link}', [TaskController::class, 'updateLink'])->name('tasks.links.update');
        Route::delete('tasks/links/{link}', [TaskController::class, 'destroyLink'])->name('tasks.links.destroy');

        // Stamps "this person has now looked at this task", which is what clears
        // the board card's admin ribbon for them and leaves it up for everyone else.
        Route::post('tasks/{task}/seen', [TaskController::class, 'markSeen'])->name('tasks.seen');

        // Task activity and comment routes
        Route::get('tasks/{task}/activities', [TaskController::class, 'getActivities'])->name('tasks.activities.index');
        Route::post('tasks/comments', [TaskController::class, 'storeComment'])->name('tasks.comments.store');

        // Subtask routes
        Route::get('tasks/{task}/subtasks', [TaskController::class, 'getSubtasks'])->name('tasks.subtasks.index');
        Route::post('tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');
        Route::post('tasks/{task}/set-parent', [TaskController::class, 'setParent'])->name('tasks.set-parent');

        // Sub-work item time tracking (Start / Pause / Done)
        Route::post('tasks/{task}/timer/start', [TaskController::class, 'startTimer'])->name('tasks.timer.start');
        Route::post('tasks/{task}/timer/pause', [TaskController::class, 'pauseTimer'])->name('tasks.timer.pause');
        Route::post('tasks/{task}/timer/done', [TaskController::class, 'completeTimer'])->name('tasks.timer.done');
        Route::prefix('report')->name('report.')->group(function () {
            // Payroll reports hub — salary overall/individual, loan, advance, payslip
            Route::get('payroll', [PayrollReportController::class, 'index'])->name('payroll');
            Route::get('payroll/print', [PayrollReportController::class, 'print'])->name('payroll.print');
            // The standing-questions overview, alongside the five filter-driven tabs.
            Route::get('payroll/overview', [PayrollReportController::class, 'overview'])->name('payroll.overview');
            Route::get('payroll/overview/export/excel', [PayrollReportController::class, 'overviewExcel'])->name('payroll.overview.export.excel');
            Route::get('payroll/overview/export/pdf', [PayrollReportController::class, 'overviewPdf'])->name('payroll.overview.export.pdf');

            Route::get('contract-flight/agent-overall', [ContractFlightReportController::class, 'agentOverall'])->name('contract-flight.agent-overall');
            Route::get('contract-flight/leg-cost', [ContractFlightReportController::class, 'legCost'])->name('contract-flight.leg-cost');

            Route::get('monthly-profit', [MonthlyProfitController::class, 'index'])->name('monthly-profit');
            Route::get('monthly-profit/data', [MonthlyProfitController::class, 'data'])->name('monthly-profit.data');
            Route::get('monthly-profit/print', [MonthlyProfitController::class, 'printView'])->name('monthly-profit.print');

            Route::get('/monthly-attendances', [ReportController::class, 'monthly_attendances'])->name('monthly-attendances');
            Route::get('/attendance/details/{attendance_id}/{date}', [ReportController::class, 'attendanceDetails'])->name('attendance.details');

            // task report
            Route::get('/task-reports', [TaskReportController::class, 'taskReport'])->name('task.reports');
            Route::post('/task-reports', [TaskReportController::class, 'taskReport'])->name('task.reports.post');
            Route::get('/task-details/{taskId}', [TaskReportController::class, 'getTaskDetails'])->name('task.details');

            // account report routes
            Route::get('general-ledger', [ReportController::class, 'generalLedgerV1'])->name('general-ledger');
            Route::get('general-ledger/print', [ReportController::class, 'generalLedgerPrint'])->name('general-ledger.print');
            Route::get('general-ledger-v2', [ReportController::class, 'generalLedgerV2'])->name('general-ledgerV2'); // version 2
            Route::get('trial-balance', [ReportController::class, 'trialBalanceV1'])->name('trial-balance');
            Route::get('trial-balance-v2', [ReportController::class, 'trialBalanceV2'])->name('trial-balanceV2'); // version 2
            Route::get('profit-loss', [ReportController::class, 'profitLossV1'])->name('profit-loss');
            Route::get('profit-loss-v2', [ReportController::class, 'profitLossV2'])->name('profit-lossV2'); // version 2
            Route::get('balance-sheet', [ReportController::class, 'balanceSheetV1'])->name('balance-sheet');
            Route::get('balance-sheet-v2', [ReportController::class, 'balanceSheetV2'])->name('balance-sheetV2'); // version 2

            // Financial statements, one company at a time (App\Services\Reports\StatementBuilder).
            // profit-loss and balance-sheet keep their routes above; their layout is upgraded in place
            // (?classic=1 shows the earlier one). The rest are new.
            Route::get('profit-loss/print', [AccountingReportController::class, 'profitLossPrint'])->name('profit-loss.print');
            Route::get('profit-loss/export', [AccountingReportController::class, 'profitLossExport'])->name('profit-loss.export');
            Route::get('balance-sheet/print', [AccountingReportController::class, 'balanceSheetPrint'])->name('balance-sheet.print');
            Route::get('balance-sheet/export', [AccountingReportController::class, 'balanceSheetExport'])->name('balance-sheet.export');
            Route::get('cash-flow', [AccountingReportController::class, 'cashFlow'])->name('cash-flow');
            Route::get('cash-flow/print', [AccountingReportController::class, 'cashFlowPrint'])->name('cash-flow.print');
            Route::get('cash-flow/export', [AccountingReportController::class, 'cashFlowExport'])->name('cash-flow.export');
            Route::get('monthly-report', [AccountingReportController::class, 'monthlyReport'])->name('monthly-report');
            Route::get('monthly-report/print', [AccountingReportController::class, 'monthlyReportPrint'])->name('monthly-report.print');
            Route::get('monthly-report/export', [AccountingReportController::class, 'monthlyReportExport'])->name('monthly-report.export');
            Route::get('ar-aging', [AccountingReportController::class, 'arAging'])->name('ar-aging');
            Route::get('ar-aging/print', [AccountingReportController::class, 'arAgingPrint'])->name('ar-aging.print');
            Route::get('ar-aging/export', [AccountingReportController::class, 'arAgingExport'])->name('ar-aging.export');
            Route::get('ap-aging', [AccountingReportController::class, 'apAging'])->name('ap-aging');
            Route::get('ap-aging/print', [AccountingReportController::class, 'apAgingPrint'])->name('ap-aging.print');
            Route::get('ap-aging/export', [AccountingReportController::class, 'apAgingExport'])->name('ap-aging.export');
            Route::get('expenses-by-category', [AccountingReportController::class, 'expensesByCategory'])->name('expenses-by-category');
            Route::get('expenses-by-category/print', [AccountingReportController::class, 'expensesByCategoryPrint'])->name('expenses-by-category.print');
            Route::get('expenses-by-category/export', [AccountingReportController::class, 'expensesByCategoryExport'])->name('expenses-by-category.export');
            Route::get('expenses-by-category/excel', [AccountingReportController::class, 'expensesByCategoryExcel'])->name('expenses-by-category.excel');
            Route::get('account-ledger', [ReportController::class, 'accountLedgerV1'])->name('account-ledger');
            Route::get('account-ledger-v2', [ReportController::class, 'accountLedgerV2'])->name('account-ledgerV2'); // version 2
            Route::get('account-statement', [ReportController::class, 'accountStatementV1'])->name('account-statement');
            Route::get('account-statement-v2', [ReportController::class, 'accountStatementV2'])->name('account-statementV2'); // version 2
            Route::get('journal-entries', [ReportController::class, 'journalEntriesV1'])->name('journal-entries');
            Route::get('journal-entries-v2', [ReportController::class, 'journalEntriesV2'])->name('journal-entriesV2'); // version 2
            Route::get('account-balances', [ReportController::class, 'accountBalancesV1'])->name('account-balances');
            Route::get('account-balances-v2', [ReportController::class, 'accountBalancesV2'])->name('account-balancesV2'); // version 2
        });

        // Notification routes
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::post('notifications/{id}/dismiss-bulletin', [NotificationController::class, 'dismissFromBulletin'])->name('notifications.dismiss-bulletin');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('notifications/bulk-delete', [NotificationController::class, 'bulkDestroy'])->name('notifications.bulk-destroy');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::resource('notices', NoticeController::class)->except('create', 'edit');

        // Trash routes
        Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
        Route::put('trash/{module}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
        Route::delete('trash/{module}/{id}/force-delete', [TrashController::class, 'forceDelete'])->name('trash.force-delete');
        Route::put('trash/{module}/restore-all', [TrashController::class, 'restoreAll'])->name('trash.restore-all');
        Route::delete('trash/{module}/empty', [TrashController::class, 'emptyModule'])->name('trash.empty-module');

        // Chat routes
        Route::post('/chat/presign', [ChatController::class, 'presignUpload'])->name('chat.presign');
        Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
        Route::get('/chat/fetch/{receiver_id}', [ChatController::class, 'fetchMessages'])->name('chat.fetch');
        Route::get('/chat/online-status', [ChatController::class, 'onlineStatus'])->name('chat.online-status');
        Route::put('/chat/message/{chat}', [ChatController::class, 'updateMessage'])->name('chat.message.update');
        Route::delete('/chat/message/{chat}', [ChatController::class, 'destroyMessage'])->name('chat.message.destroy');
    });

Route::prefix('export')->name('export.')->group(function () {
    Route::get('monthly/attendance/excel', [ReportController::class, 'monthlyAttendanceExcel'])->name('monthly.attendance.excel');
    Route::get('monthly/attendance/pdf', [ReportController::class, 'monthlyAttendancePdf'])->name('monthly.attendance.pdf');

    // Task Report Exports
    Route::get('task-report/excel', [TaskReportController::class, 'exportExcel'])->name('task.report.excel');
    Route::get('task-report/pdf', [TaskReportController::class, 'exportPdf'])->name('task.report.pdf');
});

Route::get('/test-pusher', function () {
    broadcast(new TestEvent('Hello World'));

    return 'Event broadcasted!';
});

// attendance
Route::get('attendance/sync', [ApiAttendanceController::class, 'syncAttendance']);
Route::get('/salary/view/{id}', [EmployeeSalaryController::class, 'view'])
    ->name('salary.view')
    ->middleware('signed'); // এটি লিঙ্কের নিরাপত্তা নিশ্চিত করে
Route::get('/salary/download/{id}', [EmployeeSalaryController::class, 'download'])
    ->name('salary.download')
    ->middleware('signed');
// TinyURL-style statement links use a 12-character random bearer token and
// expire in 30 days. Throttling limits automated guessing. Keep the previous
// /statement path so links already present in chats remain usable.
Route::get('/s/{token}', [PartyStatementController::class, 'shortPdf'])
    ->where('token', '[A-Za-z0-9]{8,64}')
    ->middleware('throttle:30,1')
    ->name('party-statement.short-pdf');
Route::get('/statement/{token}', [PartyStatementController::class, 'shortPdf'])
    ->where('token', '[A-Za-z0-9]{8,64}')
    ->middleware('throttle:30,1');

// Function to redirect user to their respective dashboard based on role

// function redirectToRoleDashboard($user)
// {
//     if ($user->hasRole('super admin')) {
//         return redirect()->route('role.dashboard', ['role' => 'super-admin']);
//     } elseif ($user->hasRole('admin')) {
//         return redirect()->route('role.dashboard', ['role' => 'admin']);
//     } elseif ($user->hasRole('vendor')) {
//         return redirect()->route('role.dashboard', ['role' => 'vendor']);
//     } elseif ($user->hasRole('agent')) {
//         return redirect()->route('role.dashboard', ['role' => 'agent']);
//     } else {
//         return redirect()->route('login');
//     }
// }
