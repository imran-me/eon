<?php

use App\Http\Controllers\LeaveController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Dashboard\BankController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\PassportHolderController;
use App\Http\Controllers\Dashboard\VendorController;
use App\Http\Controllers\Dashboard\CustomerRoleController;
use App\Http\Controllers\Dashboard\PortalManagementController;
use App\Http\Controllers\Dashboard\PayrolController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\RouteManagementController;
use App\Http\Controllers\Dashboard\TicketPurchaseController;
use App\Http\Controllers\Dashboard\TicketSellController;
use App\Http\Controllers\Dashboard\SmsMarketingController;
use App\Http\Controllers\Dashboard\EmailMarketingController;
use App\Http\Controllers\Dashboard\WhatsappMarketingController;
use App\Http\Controllers\Dashboard\PassportHolderCategoryController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\Dashboard\TicketSaleController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\SalaryTemplateController;
use App\Http\Controllers\SaleRecordController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\AdvanceSalaryController;
use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Dashboard\BankControllerV2;
use App\Http\Controllers\Dashboard\FinancingController;
use App\Http\Controllers\Dashboard\BankTransferController;
use App\Http\Controllers\Dashboard\ContractManageController;
use App\Http\Controllers\Dashboard\ContractTypeController;
use App\Http\Controllers\Dashboard\DealManageController;
use App\Http\Controllers\Dashboard\EstimateController;
use App\Http\Controllers\Dashboard\LeadFollowupController;
use App\Http\Controllers\Dashboard\LeadManagerController;
use App\Http\Controllers\Dashboard\LeadReminderController;
use App\Http\Controllers\Dashboard\LeadSourceController;
use App\Http\Controllers\Dashboard\PassportHolderControllerV2;
use App\Http\Controllers\Dashboard\PassportHolderCategoryControllerV2;
use App\Http\Controllers\Dashboard\ProjectCategoryController;
use App\Http\Controllers\Dashboard\ProjectManageController;
use App\Http\Controllers\Dashboard\PromotionController;
use App\Http\Controllers\Dashboard\ProposalManageController;
use App\Http\Controllers\Dashboard\ResignationController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\SupportTicketController;
use App\Http\Controllers\Dashboard\TicketDepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseBudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseSubCategoryController;
use App\Http\Controllers\InvoiceSettingController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\TaskReportController;
use App\Http\Controllers\Dashboard\MonthlyProfitController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Task\BoardController;
use App\Http\Controllers\Task\ColumnController;
use App\Http\Controllers\Task\LabelController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Task\WorkSpaceUserController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UnitController;
use App\Models\Board;
use Spatie\Permission\Models\Role;
use App\Events\TestEvent;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\Dashboard\AccountController;
use App\Http\Controllers\Dashboard\JournalController;
use App\Http\Controllers\Dashboard\VoucherController;
use App\Http\Controllers\VisaCategoryController;
use App\Http\Controllers\VisaProcessingController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\SsoController;

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
Route::get('/two-factor/verify', [\App\Http\Controllers\Auth\TwoFactorController::class, 'showVerify'])->name('two-factor.verify');
Route::post('/two-factor/verify', [\App\Http\Controllers\Auth\TwoFactorController::class, 'verify'])->name('two-factor.verify.post');

// 2FA setup/disable routes (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/two-factor/setup', [\App\Http\Controllers\Auth\TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/enable', [\App\Http\Controllers\Auth\TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/disable', [\App\Http\Controllers\Auth\TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

Route::get('server_status', [ApiAttendanceController::class, 'serverStatus']);
Route::get('device/{data}', [ApiAttendanceController::class, 'device']);
Route::get('attendance_log/{data}', [ApiAttendanceController::class, 'attendanceLog']);

$roles = Role::pluck('name')->map(fn($name) => Str::slug($name))->implode('|');
// Super Admin routes
// Super Admin routes
Route::middleware(['auth', \App\Http\Middleware\EnsurePanelRoleIsPermitted::class])
->prefix('{role}')
->name('role.')
->where(['role' => $roles])
->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/todo-list', [DashboardController::class, 'todoList'])->name('dashboard.todo-list');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('change-password', [LoginController::class, 'updatePassword'])->name('change.password');
    Route::get('user/documents', [UserController::class, 'documents'])->name('user.documents');
    Route::post('user/{user}/documents', [UserController::class, 'updateDocuments'])->name('user.documents.update');
    Route::get('user/{user}/summary', [UserController::class, 'summary'])->name('user.summary');
    Route::get('user/{user}/salary-transaction-report/print', [UserController::class, 'salaryTransactionReportPrint'])->name('user.salary-transaction-report.print');
    Route::get('user/{user}/payslips-report/print', [UserController::class, 'payslipsReportPrint'])->name('user.payslips-report.print');
    Route::post('user/{user}/ledger/bonus', [\App\Http\Controllers\EmployeeLedgerController::class, 'storeBonus'])->name('user.ledger.bonus');
    Route::post('user/{user}/ledger/opening-balance', [\App\Http\Controllers\EmployeeLedgerController::class, 'storeOpeningBalance'])->name('user.ledger.opening-balance');
    Route::post('user/{user}/leave-encashment-opening', [\App\Http\Controllers\EmployeeLedgerController::class, 'storeLeaveEncashmentOpening'])->name('user.leave-encashment-opening');
    Route::post('user/{user}/pay-due-amount', [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'payDueAmount'])->name('user.pay-due-amount');
    Route::get('user/search', [UserController::class, 'search'])->name('user.search');
    Route::resource('user', UserController::class);
    Route::resource('promotions', PromotionController::class)->except('show');
    Route::post('promotions/approve/{id}', [PromotionController::class, 'approve'])->name('promotions.approve');
    Route::post('promotions/reject/{id}', [PromotionController::class, 'reject'])->name('promotions.reject');
    Route::resource('passport-holder', PassportHolderControllerV2::class);
    Route::resource('passport-holder-category', PassportHolderCategoryControllerV2::class);
    Route::resource('visa-category', VisaCategoryController::class)->except('show');
    Route::resource('visa', VisaProcessingController::class)->except('show');
    // Visa Sales — static routes must come before the {visaSale} binding
    Route::get('visa-sales/applications', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'fetchApplications'])->name('visa-sales.applications');
    Route::get('visa-sales/other-services', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'fetchOtherServices'])->name('visa-sales.other-services');
    Route::get('visa-sales/{visaSale}/voucher', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'voucherPrint'])->name('visa-sales.voucher');
    Route::post('visa-sales/{visaSale}/email', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'emailVoucher'])->name('visa-sales.email');
    Route::get('visa-sales/{visaSale}/edit', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'edit'])->name('visa-sales.edit');
    Route::put('visa-sales/{visaSale}', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'update'])->name('visa-sales.update');
    Route::patch('visa-sales/{visaSale}/payment', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'makePayment'])->name('visa-sales.payment');
    Route::get('visa-sales/{visaSale}', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'show'])->name('visa-sales.show');
    Route::get('visa-sales', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'index'])->name('visa-sales.index');
    Route::post('visa-sales', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'store'])->name('visa-sales.store');
    Route::delete('visa-sales/{visaSale}', [\App\Http\Controllers\Dashboard\VisaSaleController::class, 'destroy'])->name('visa-sales.destroy');
    // ── Comments (polymorphic — ticket_sale, visa_sale, contract_flight, contract_file) ──
    Route::get('comments',              [\App\Http\Controllers\Dashboard\CommentController::class, 'list'])->name('comments.list');
    Route::post('comments',             [\App\Http\Controllers\Dashboard\CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}', [\App\Http\Controllers\Dashboard\CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('visa-doc-tracker', [VisaProcessingController::class, 'docTracker'])->name('visa.doc-tracker');
    Route::post('visa/{visa}/doc-toggle', [VisaProcessingController::class, 'toggleDocStatus'])->name('visa.doc-toggle');
    Route::get('visa/{visa}/attachments', [VisaProcessingController::class, 'getAttachments'])->name('visa.attachments');
    Route::get('visa/{visa}/details', [VisaProcessingController::class, 'details'])->name('visa.details');
    Route::delete('visa/{visa}/attachment/{attachment}', [VisaProcessingController::class, 'deleteAttachment'])->name('visa.attachment.delete');
    Route::patch('visa/{visa}/status', [VisaProcessingController::class, 'updateStatus'])->name('visa.status');
    Route::post('visa/{visa}/pay-vendor', [VisaProcessingController::class, 'payVendor'])->name('visa.pay-vendor');
    Route::resource('other-visa-services', \App\Http\Controllers\Dashboard\OtherVisaServiceController::class)->except(['show', 'create', 'edit']);
    Route::resource('other-service-types', \App\Http\Controllers\Dashboard\OtherServiceTypeController::class)->except(['show', 'create', 'edit']);
    Route::resource('contract-file-categories', \App\Http\Controllers\Dashboard\ContractFileCategoryController::class)->except(['show', 'create', 'edit']);
    Route::resource('contract-files', \App\Http\Controllers\Dashboard\ContractFileController::class)->except(['show', 'create', 'edit']);
    Route::get('contract-file-sales/{contractFileSale}/voucher', [\App\Http\Controllers\Dashboard\ContractFileSaleController::class, 'voucherPrint'])->name('contract-file-sales.voucher');
    Route::get('contract-file-sales/{contractFileSale}/edit', [\App\Http\Controllers\Dashboard\ContractFileSaleController::class, 'edit'])->name('contract-file-sales.edit');
    Route::patch('contract-file-sales/{contractFileSale}/payment', [\App\Http\Controllers\Dashboard\ContractFileSaleController::class, 'makePayment'])->name('contract-file-sales.payment');
    Route::resource('contract-file-sales', \App\Http\Controllers\Dashboard\ContractFileSaleController::class)->except(['create', 'edit']);
    Route::post('contract-files/{contractFile}/pay-vendor', [\App\Http\Controllers\Dashboard\ContractFileController::class, 'payVendor'])->name('contract-files.pay-vendor');
    Route::resource('flight-category-types', \App\Http\Controllers\Dashboard\FlightCategoryTypeController::class)->except(['show', 'create', 'edit']);
    Route::get('flight-price-presets/match', [\App\Http\Controllers\Dashboard\FlightPricePresetController::class, 'match'])->name('flight-price-presets.match');
    Route::resource('flight-price-presets', \App\Http\Controllers\Dashboard\FlightPricePresetController::class)->except(['show', 'create', 'edit']);
    Route::resource('flight-officers', \App\Http\Controllers\Dashboard\FlightOfficerController::class)->except(['show', 'create', 'edit']);
    Route::resource('flight-categories', \App\Http\Controllers\Dashboard\FlightCategoryController::class)->except(['show', 'create', 'edit']);
    Route::resource('flight-schedules', \App\Http\Controllers\Dashboard\FlightScheduleController::class)->except(['show', 'create', 'edit']);
    Route::get('contract-flight-sales/{contractFlightBooking}/invoice', [\App\Http\Controllers\Dashboard\ContractFlightBookingController::class, 'invoice'])->name('contract-flight-sales.invoice');
    Route::resource('contract-flight-sales', \App\Http\Controllers\Dashboard\ContractFlightBookingController::class)->except(['show', 'create', 'edit']);
    Route::get('contract-flights/{contractFlight}/invoice', [\App\Http\Controllers\Dashboard\ManageFlightController::class, 'invoice'])->name('contract-flights.invoice');
    Route::post('contract-flights/{contractFlight}/pay-vendor', [\App\Http\Controllers\Dashboard\ManageFlightController::class, 'payVendor'])->name('contract-flights.pay-vendor');
    Route::resource('contract-flights', \App\Http\Controllers\Dashboard\ManageFlightController::class)->except(['show', 'create', 'edit']);
    // Party list exports — must stay above the resources, otherwise
    // `vendor/export` matches the resource's `vendor/{vendor}` show route.
    Route::get('vendor/export/excel', [VendorController::class, 'exportExcel'])->name('vendor.export.excel');
    Route::get('vendor/export/pdf', [VendorController::class, 'exportPdf'])->name('vendor.export.pdf');
    Route::get('agent/export/excel', [AgentController::class, 'exportExcel'])->name('agent.export.excel');
    Route::get('agent/export/pdf', [AgentController::class, 'exportPdf'])->name('agent.export.pdf');
    Route::get('customer/export/excel', [CustomerRoleController::class, 'exportExcel'])->name('customer.export.excel');
    Route::get('customer/export/pdf', [CustomerRoleController::class, 'exportPdf'])->name('customer.export.pdf');
    Route::resource('vendor', VendorController::class);
    Route::resource('agent', AgentController::class);
    Route::resource('customer', CustomerRoleController::class);
    Route::get('party-statement', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'index'])->name('party-statement.index');
    Route::get('party-statement/search', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'search'])->name('party-statement.search');
    Route::get('party-statement/load', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'load'])->name('party-statement.load');
    Route::post('party-statement/payment', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'recordPayment'])->name('party-statement.payment');
    Route::post('party-statement/invoice', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'createInvoice'])->name('party-statement.invoice');
    Route::post('party-statement/opening-balance', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'setOpeningBalance'])->name('party-statement.opening-balance');
    Route::get('party-statement/export/excel', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'exportExcel'])->name('party-statement.excel');
    Route::get('party-statement/export/pdf', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'exportPdf'])->name('party-statement.pdf');
    Route::get('party-statement/invoice/{partyInvoice}/pdf', [\App\Http\Controllers\Dashboard\PartyStatementController::class, 'invoicePdf'])->name('party-statement.invoice-pdf');
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
    // Same figures as the page, as JSON — lets a cash entry refresh the
    // dashboard in place instead of reloading it. See dashboardData().
    Route::get('banks/dashboard/data', [BankControllerV2::class, 'dashboardData'])->name('banks.dashboard.data');
    Route::get('banks/search', [BankControllerV2::class, 'search'])->name('banks.search');
    Route::get('banks/load', [BankControllerV2::class, 'load'])->name('banks.load');
    Route::resource('banks', BankControllerV2::class);
    Route::get('banks/{bank}/statement', [BankControllerV2::class, 'statement'])->name('banks.statement');
    Route::get('banks/{bank}/statement/pdf', [BankControllerV2::class, 'printStatement'])->name('banks.statement.pdf');
    Route::post('banks/{bank}/transaction', [BankControllerV2::class, 'recordTransaction'])->name('banks.transaction');
    Route::post('banks/journal/{journalEntry}/reverse', [BankControllerV2::class, 'reverseTransaction'])->name('banks.journal.reverse');
    // Remarks only — the description is recomposed server-side from the bank and
    // direction. Nothing numeric is editable; a wrong amount is fixed by reversing.
    Route::post('banks/journal/{journalEntry}/remarks', [BankControllerV2::class, 'updateRemarks'])->name('banks.journal.remarks');

    // ── Capital & Financing — the loan book ──────────────────────────────
    // Three books on one desk: lent out, borrowed, and the employee book
    // mirrored read-only from Payroll. Writes no journal entries by the owner's
    // rule; see the create_financing_tables migration.
    Route::get('financing', [FinancingController::class, 'index'])->name('financing.index');
    Route::post('financing', [FinancingController::class, 'store'])->name('financing.store');
    // Capital movements — owner money in and out. Declared BEFORE the
    // {financing} wildcard so 'capital' is never swallowed as a loan id.
    Route::post('financing/capital', [FinancingController::class, 'storeCapital'])->name('financing.capital.store');
    Route::delete('financing/capital/{capital}', [FinancingController::class, 'destroyCapital'])->name('financing.capital.destroy');
    // Categories and sub-categories — one self-parenting table, so these three
    // cover both levels. Also before the {financing} wildcard.
    Route::post('financing/categories', [FinancingController::class, 'storeCategory'])->name('financing.categories.store');
    Route::put('financing/categories/{category}', [FinancingController::class, 'updateCategory'])->name('financing.categories.update');
    Route::delete('financing/categories/{category}', [FinancingController::class, 'destroyCategory'])->name('financing.categories.destroy');
    Route::get('financing/{financing}', [FinancingController::class, 'show'])->name('financing.show');
    Route::put('financing/{financing}', [FinancingController::class, 'update'])->name('financing.update');
    Route::post('financing/{financing}/regenerate', [FinancingController::class, 'regenerate'])->name('financing.regenerate');
    Route::post('financing/{financing}/payment', [FinancingController::class, 'recordPayment'])->name('financing.payment');
    // Money going OUT on a running account — the counterpart to payment, which
    // brings it back. Only a running account accepts one; the controller refuses
    // it on a fixed loan, whose principal is handed over once at setup.
    Route::post('financing/{financing}/drawdown', [FinancingController::class, 'recordDrawdown'])->name('financing.drawdown');
    Route::delete('financing/{financing}', [FinancingController::class, 'destroy'])->name('financing.destroy');

    Route::resource('ticket-direct-sale', \App\Http\Controllers\Dashboard\TicketDirectSaleController::class)->names('ticket-direct-sale');
    Route::get('ticket-invoice-lookup', [\App\Http\Controllers\Dashboard\TicketDirectSaleController::class, 'invoiceLookup'])->name('ticket-invoice-lookup');
    Route::post('ticket-quick-create-route', [\App\Http\Controllers\Dashboard\TicketDirectSaleController::class, 'quickCreateTicket'])->name('ticket-quick-create-route');
    Route::post('ticket-refund', [\App\Http\Controllers\Dashboard\TicketDirectSaleController::class, 'storeRefund'])->name('ticket-refund.store');
    Route::post('ticket-reissue', [\App\Http\Controllers\Dashboard\TicketDirectSaleController::class, 'storeReissue'])->name('ticket-reissue.store');

    Route::group(['prefix' => 'vouchers', 'as' => 'vouchers.'], function () {
        Route::get('/', [VoucherController::class, 'index'])->name('index');
        Route::post('/store', [VoucherController::class, 'store'])->name('store');
        Route::post('/update', [VoucherController::class, 'update'])->name('update'); // This was the missing link
        Route::delete('/destroy', [VoucherController::class, 'destroy'])->name('destroy');
    });
    Route::resource('accounts', AccountController::class);
    Route::resource('journals', JournalController::class);
    Route::get('journals/{journal}/voucher', [JournalController::class, 'voucher'])->name('journals.voucher');
    Route::get('journals/{journal}/party-voucher', [JournalController::class, 'partyVoucher'])->name('journals.party-voucher');
    Route::resource('bank_transfers', BankTransferController::class);
    Route::resource('payrol', PayrolController::class);
    Route::resource('route', RouteManagementController::class);
    Route::resource('airlines', \App\Http\Controllers\AirlineController::class)->except('show');
    Route::resource('tickets', TicketController::class);
    Route::get('get-ticket-edit-modal', [TicketController::class, 'getTicketEditModal'])->name('get-ticket-edit-modal');
    Route::get('ticket-sales/available-purchases', [TicketSaleController::class, 'availablePurchases'])->name('ticket-sales.available-purchases');
    Route::get('ticket-sales/{ticket_sale}/detail', [TicketSaleController::class, 'detail'])->name('ticket-sales.detail');
    Route::resource('ticket-sales', TicketSaleController::class);
    Route::post('ticket-sales/make/payment/{id}', [TicketSaleController::class, 'make_payment'])->name('ticket-sales.make.payment');
    Route::resource('ticket-purchase', TicketPurchaseController::class);
    Route::post('ticket-purchase/make/payment/{id}', [TicketPurchaseController::class, 'make_payment'])->name('ticket-purchase.make.payment');
    
    Route::post('payment-schedules/adhoc', [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'storeAdHoc'])->name('payment-schedules.adhoc');
    Route::get('headline-notices', [\App\Http\Controllers\Dashboard\HeadlineNoticeController::class, 'index'])->name('headline-notices.index');
    Route::get('payment-schedules/parties-by-type', [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'partiesByType'])->name('payment-schedules.parties-by-type');
    Route::get('payment-schedules/party-types-by-company', [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'partyTypesByCompany'])->name('payment-schedules.party-types-by-company');
    Route::get('payment-schedules/project-categories-by-company', [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'projectCategoriesByCompany'])->name('payment-schedules.project-categories-by-company');
    Route::resource('payment-schedules', \App\Http\Controllers\Dashboard\PaymentScheduleController::class)->only(['index', 'store', 'destroy']);
    Route::patch('payment-schedules/{schedule}/cancel',     [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'cancel'])->name('payment-schedules.cancel');
    Route::patch('payment-schedules/{schedule}/approve',    [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'approve'])->name('payment-schedules.approve');
    Route::patch('payment-schedules/{schedule}/reject',     [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'reject'])->name('payment-schedules.reject');
    Route::patch('payment-schedules/{schedule}/reschedule', [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'reschedule'])->name('payment-schedules.reschedule');
    Route::patch('payment-schedules/{schedule}/mark-paid',  [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'markPaid'])->name('payment-schedules.mark-paid');
    Route::get('payment-schedules/{schedule}/voucher',      [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'voucher'])->name('payment-schedules.voucher');
    Route::patch('payment-schedules/{schedule}/priority',   [\App\Http\Controllers\Dashboard\PaymentScheduleController::class, 'setPriority'])->name('payment-schedules.priority');
    
    Route::resource('sms-marketing', SmsMarketingController::class);
    Route::resource('email-marketing', EmailMarketingController::class);
    Route::resource('whatsapp-marketing', WhatsappMarketingController::class);

    Route::get('site-settings', [SiteSettingController::class, 'index'])->name('site-settings.index');
    Route::put('site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');
    
    Route::get('demo-data', [\App\Http\Controllers\Dashboard\DemoDataController::class, 'index'])->name('demo-data.index');
    Route::delete('demo-data', [\App\Http\Controllers\Dashboard\DemoDataController::class, 'destroy'])->name('demo-data.destroy');
    
    Route::resource('countries', CountryController::class)->except('show');
    Route::resource('states', StateController::class)->except('show');
    Route::resource('airport', AirportController::class)->except('show');
    Route::get('get-states-by-country', [StateController::class, 'getStatesByCountry'])->name('get-states-by-country');
    Route::resource('company-settings', CompanyController::class)->except('show');
    Route::get('company/{company}/dashboard', [\App\Http\Controllers\CompanyDashboardController::class, 'show'])->name('company.dashboard');
    // Wood Art Interiors routes live in the module: Modules/WoodArt/routes/web.php
    Route::resource('sms_templates', SmsTemplateController::class)->except('show');
    Route::resource('departments', DepartmentController::class)->except('show');
    Route::resource('designations', DesignationController::class)->except('show');
    Route::resource('shifts', ShiftController::class)->except('show');
    Route::resource('holidays', HolidayController::class)->except('show');
    Route::resource('attendances', AttendanceController::class)->except('show');
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

    // Employee Request & Requirement System — Phase 1 + Phase 2 + Phase 3
    // Static routes BEFORE resource (avoids {id} wildcard capture)
    Route::get('employee-requests/self-service',     [\App\Http\Controllers\EmployeeRequestController::class, 'selfService'])->name('employee-requests.self-service');
    Route::get('employee-requests/report',           [\App\Http\Controllers\EmployeeRequestController::class, 'report'])->name('employee-requests.report');
    Route::resource('employee-requests', \App\Http\Controllers\EmployeeRequestController::class)->except('show');
    Route::post('employee-requests/{id}/approve',    [\App\Http\Controllers\EmployeeRequestController::class, 'approve'])->name('employee-requests.approve');
    Route::post('employee-requests/{id}/reject',     [\App\Http\Controllers\EmployeeRequestController::class, 'reject'])->name('employee-requests.reject');
    Route::post('employee-requests/{id}/fulfill',    [\App\Http\Controllers\EmployeeRequestController::class, 'fulfill'])->name('employee-requests.fulfill');
    Route::post('employee-requests/{id}/disburse',   [\App\Http\Controllers\EmployeeRequestController::class, 'disburse'])->name('employee-requests.disburse');
    Route::post('employee-requests/{id}/recover',    [\App\Http\Controllers\EmployeeRequestController::class, 'recover'])->name('employee-requests.recover');
    // Phase 3
    Route::post('employee-requests/{id}/fast-track', [\App\Http\Controllers\EmployeeRequestController::class, 'fastTrack'])->name('employee-requests.fast-track');
    Route::post('employee-requests/{id}/close',      [\App\Http\Controllers\EmployeeRequestController::class, 'close'])->name('employee-requests.close');
    Route::get('employee-requests/{id}/noc-pdf',     [\App\Http\Controllers\EmployeeRequestController::class, 'generateNoc'])->name('employee-requests.noc-pdf');
    Route::get('require-assignments/overdue',        [\App\Http\Controllers\RequireAssignmentController::class, 'overdueList'])->name('require-assignments.overdue');
    Route::put('require-assignments/{id}/verify',    [\App\Http\Controllers\RequireAssignmentController::class, 'verify'])->name('require-assignments.verify');
    Route::put('require-assignments/{id}/escalate',  [\App\Http\Controllers\RequireAssignmentController::class, 'escalate'])->name('require-assignments.escalate');
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
    Route::get('expense-classification', [\App\Http\Controllers\ExpenseClassificationController::class, 'index'])
        ->name('expense-classification.index');

    // The old per-entity list URLs still resolve — bookmarks, and the two
    // non-AJAX redirect()->route() fallbacks inside the category and
    // sub-category controllers, both land here. Declared before the resources so
    // they win the GET; the resources keep store/update/destroy. A controller
    // action rather than Route::redirect() because the destination has to carry
    // the {role} prefix, which a static destination string cannot.
    foreach (['expense-departments', 'expense-categories', 'expense-subcategories'] as $legacyExpenseList) {
        Route::get($legacyExpenseList, [\App\Http\Controllers\ExpenseClassificationController::class, 'redirectLegacy'])
            ->name($legacyExpenseList . '.index');
    }

    // The expense desk's own departments — separate from HR's, and company-scoped
    // so the expense form can fill the company in from one.
    Route::resource('expense-departments', \App\Http\Controllers\ExpenseDepartmentController::class)
        ->only(['store', 'update', 'destroy']);
    Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show', 'index']);
    Route::resource('party-types', \App\Http\Controllers\PartyTypeController::class)->except(['show', 'create', 'edit']);
    Route::resource('expense-subcategories', ExpenseSubCategoryController::class)->except(['show', 'index']);
    Route::get('get-expense-sub-category', [ExpenseSubCategoryController::class, 'getExpenseSubCategory'])->name('get-expense-sub-category');

    // Petty cash — money handed to a custodian and not yet spent. Issue and
    // return are their own actions rather than a generic update because each one
    // writes a journal entry; editing a row would not.
    Route::get('petty-cash', [\App\Http\Controllers\PettyCashController::class, 'index'])->name('petty-cash.index');

    // The daily cost fund — how much a company MAY spend in cash today. Declared
    // before the {float} routes so a literal path can never be read as a float id,
    // and kept on the petty cash desk rather than inside it because it moves no
    // money: it writes no journal entry at all, it only sets a ceiling.
    Route::post('petty-cash/daily-fund', [\App\Http\Controllers\PettyCashController::class, 'saveDailyFunds'])
        ->name('petty-cash.daily-fund.save');
    Route::get('petty-cash/daily-fund/{company}/breakdown', [\App\Http\Controllers\PettyCashController::class, 'dailyFundBreakdown'])
        ->name('petty-cash.daily-fund.breakdown');

    Route::post('petty-cash', [\App\Http\Controllers\PettyCashController::class, 'store'])->name('petty-cash.store');
    // Declared ABOVE petty-cash/{float}, or "report" would be read as a float id.
    Route::get('petty-cash/report', [\App\Http\Controllers\PettyCashController::class, 'report'])->name('petty-cash.report');
    Route::get('petty-cash/report/print', [\App\Http\Controllers\PettyCashController::class, 'reportPrint'])->name('petty-cash.report.print');

    Route::get('petty-cash/{float}/statement', [\App\Http\Controllers\PettyCashController::class, 'statement'])->name('petty-cash.statement');
    // "print" in the path is load-bearing, same as the expense report's export:
    // layout/app.blade.php's speculation rules skip these, so hovering the link
    // never makes the server build a statement nobody asked for.
    Route::get('petty-cash/{float}/statement/print', [\App\Http\Controllers\PettyCashController::class, 'printStatement'])->name('petty-cash.statement.print');
    Route::put('petty-cash/{float}', [\App\Http\Controllers\PettyCashController::class, 'update'])->name('petty-cash.update');
    Route::post('petty-cash/{float}/issue', [\App\Http\Controllers\PettyCashController::class, 'issue'])->name('petty-cash.issue');
    Route::post('petty-cash/{float}/return', [\App\Http\Controllers\PettyCashController::class, 'returnCash'])->name('petty-cash.return');
    Route::delete('petty-cash/{float}', [\App\Http\Controllers\PettyCashController::class, 'destroy'])->name('petty-cash.destroy');

    // Paying staff back for what they spent themselves. Its own path rather than
    // under expenses/ so it cannot be caught by `expenses/{expense}` below, and
    // because a payment is not an expense — it settles one.
    Route::get('employee-reimbursements', [\App\Http\Controllers\EmployeeReimbursementController::class, 'index'])->name('employee-reimbursements.index');
    Route::post('employee-reimbursements', [\App\Http\Controllers\EmployeeReimbursementController::class, 'store'])->name('employee-reimbursements.store');
    Route::post('employee-reimbursements/{reimbursement}/reverse', [\App\Http\Controllers\EmployeeReimbursementController::class, 'reverse'])->name('employee-reimbursements.reverse');

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

    // Subscriptions, EMI & Renewals — the screen only, no data layer yet.
    // Declared HERE, above Route::resource('expenses'), for the same reason
    // budget-setup and report are: `expenses/{expense}` would otherwise match
    // `expenses/subscriptions` first and hand "subscriptions" over as an id.
    Route::get('expenses/subscriptions', [\App\Http\Controllers\ExpenseSubscriptionController::class, 'index'])
        ->name('expenses.subscriptions');

    // Read by the expense form's cash-limit meter whenever the date changes.
    Route::get('expenses/daily-fund', [ExpenseController::class, 'dailyFund'])->name('expenses.daily-fund');
    Route::get('expenses/print', [ExpenseController::class, 'printList'])->name('expenses.print');
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

    //role
    Route::resource('role-permission', RoleController::class);

     // INVOICE SETTINGS ROUTE     
     Route::resource('invoice-templates', \App\Http\Controllers\InvoiceTemplateController::class);
     Route::post('invoice-templates/{template}/duplicate', [\App\Http\Controllers\InvoiceTemplateController::class, 'duplicate'])->name('invoice-templates.duplicate');
     Route::get('invoice-templates/{template}/preview', [\App\Http\Controllers\InvoiceTemplateController::class, 'preview'])->name('invoice-templates.preview');

    //CRM modules
    Route::get('crm/dashboard', [\App\Http\Controllers\Dashboard\CrmDashboardController::class, 'index'])->name('crm.dashboard');
    Route::resource('lead-manager', LeadManagerController::class)->except('create','show','edit');
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
    
    Route::resource('lead-source', LeadSourceController::class)->except('create','show', 'edit');
    Route::resource('lead-reminders', LeadReminderController::class)->except('create','show', 'edit');
    Route::resource('lead-followup', LeadFollowupController::class)->except('create','show', 'edit');
    Route::resource('project-categories', ProjectCategoryController::class)->except('create','show', 'edit');
    Route::resource('contract-types', ContractTypeController::class)->except('create','show', 'edit');
    Route::resource('deals', DealManageController::class)->except('create','show', 'edit');
    Route::resource('proposals', ProposalManageController::class)->except('create','show', 'edit');
    Route::resource('projects', ProjectManageController::class)->except('create', 'edit');
    Route::get('projects/ajax/department-types', [ProjectManageController::class, 'getProjectDepartments'])->name('projects.ajax.department-types');
    Route::get('projects/ajax/field-definitions', [ProjectManageController::class, 'getFieldDefinitions'])->name('projects.ajax.field-definitions');
    Route::resource('require-types', \App\Http\Controllers\Dashboard\RequireTypeController::class)->except('create', 'edit', 'show');
    Route::get('require-types/{requireType}/fields', [\App\Http\Controllers\Dashboard\RequireTypeController::class, 'getFields'])->name('require-types.fields');
    Route::post('require-types/{requireType}/fields', [\App\Http\Controllers\Dashboard\RequireTypeController::class, 'storeField'])->name('require-types.fields.store');
    Route::put('project-field-definitions/{fieldDefinition}', [\App\Http\Controllers\Dashboard\RequireTypeController::class, 'updateField'])->name('project-field-definitions.update');
    Route::delete('project-field-definitions/{fieldDefinition}', [\App\Http\Controllers\Dashboard\RequireTypeController::class, 'destroyField'])->name('project-field-definitions.destroy');
    Route::resource('task-projects', \App\Http\Controllers\Dashboard\TaskProjectController::class)->only('index');
    Route::resource('contracts', ContractManageController::class)->except('create','show', 'edit');
    Route::resource('ticket-departments', TicketDepartmentController::class)->except('create','show', 'edit');
    Route::resource('support-tickets', SupportTicketController::class)->except('create', 'edit');

    Route::post('support-tickets/{ticket}/replies', [SupportTicketController::class, 'storeReply'])->name('support-tickets.replies.store');

    // Office Todos
    Route::get('office-todos/{id}/edit', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'edit'])->name('office-todos.edit-data');
    Route::patch('office-todos/{id}/my-status', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'updateMyStatus'])->name('office-todos.my-status');
    Route::patch('office-todos/{id}/quick-status', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'quickStatus'])->name('office-todos.quick-status');
    Route::delete('office-todos/destroy', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'destroy'])->name('office-todos.destroy');
    Route::get('office-todos/{id}/checklists', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'getChecklists'])->name('office-todos.checklists');
    Route::patch('office-todos/{todo}/checklists/{checklist}/toggle', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'toggleChecklist'])->name('office-todos.checklist.toggle');
    Route::patch('office-todos/{todo}/checklists/reorder', [\App\Http\Controllers\Dashboard\OfficeTodoController::class, 'reorderChecklists'])->name('office-todos.checklist.reorder');
    Route::resource('office-todos', \App\Http\Controllers\Dashboard\OfficeTodoController::class)->except(['create', 'show', 'destroy']);
    
    Route::put('support-ticket-replies/{reply}', [SupportTicketController::class, 'updateReply'])->name('support-tickets.replies.update');
    Route::delete('support-ticket-replies/{reply}', [SupportTicketController::class, 'destroyReply'])->name('support-tickets.replies.destroy');
    Route::get('support-tickets/attachments/{filename}', [SupportTicketController::class, 'downloadAttachment'])->name('support-tickets.attachment.download');

        Route::resource("estimates", EstimateController::class)->except(
            "create",
            "edit",
        );

        //Task modules
        Route::get("workspace-users/project/{project_id}", [
            WorkSpaceUserController::class,
            "getProjectUsers",
        ])->name("workspace-users.get-project-users");
        Route::resource(
            "workspace-users",
            WorkSpaceUserController::class,
        )->except("create", "show", "edit");
        Route::resource("boards", BoardController::class)->except(
            "create",
            "show",
        );
        Route::post("boards/update-status/{id}", [
            BoardController::class,
            "updateStatus",
        ])->name("boards.updateStatus");
        Route::resource("columns", ColumnController::class)->except(
            "create",
            "show",
            "edit",
        );
        Route::resource("labels", LabelController::class)->except(
            "create",
            "show",
            "edit",
        );
        // Task custom routes (must come before resource)
        Route::get('tasks', function () {
                $role = request()->route('role');
                $user = Auth::user();

                // Pick a specific board for super admin
                if ($role === 'super-admin' || $user->hasRole('super admin')) {
                    
                    $board = Board::query()->where('id', 24)->first();
                    if (!$board) {
                        $board = Board::query()->first();
                    }
                    
                }else{
                    $query = Board::query();

                    $userId = (string) $user->id;
                    $query->whereHas('project', function ($q) use ($userId) {
                        $q->whereJsonContains('team_members', $userId);
                    });
                    
                    $boardId = \App\Models\Task::whereHas('users', fn($q) => $q->where('users.id', $user->id))
                    ->whereNotNull('board_id')
                    ->latest('created_at')
                    ->value('board_id');
                    
                    $board = $boardId ? Board::find($boardId) : null;
                }

                if (!$board) {
                    return redirect()->route('role.boards.index', $role)
                    ->with('error', 'No boards available. Please contact your administrator.');
                }

                return redirect()->route('role.tasks.board', ['role' => $role, 'board' => $board->id]);

            })->name('tasks.index');

            Route::get('board/{board}', [TaskController::class, 'index'])->name('tasks.board');
            Route::post('tasks/{task}/copy', [TaskController::class, 'copy'])->name('tasks.copy');
            // Subtask search — must come before resource to avoid {task} conflict
            Route::get('tasks/search', [TaskController::class, 'searchTasks'])->name('tasks.search');

            Route::resource('tasks', TaskController::class)->except('index','create', 'edit');

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

    // Task activity and comment routes
    Route::get('tasks/{task}/activities', [TaskController::class, 'getActivities'])->name('tasks.activities.index');
    Route::post('tasks/comments', [TaskController::class, 'storeComment'])->name('tasks.comments.store');

    // Subtask routes
    Route::get('tasks/{task}/subtasks', [TaskController::class, 'getSubtasks'])->name('tasks.subtasks.index');
    Route::post('tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');
    Route::post('tasks/{task}/set-parent', [TaskController::class, 'setParent'])->name('tasks.set-parent');
    Route::prefix('report')->name('report.')->group(function () {
        // Payroll reports hub — salary overall/individual, loan, advance, payslip
        Route::get('payroll',       [\App\Http\Controllers\Report\PayrollReportController::class, 'index'])->name('payroll');
        Route::get('payroll/print', [\App\Http\Controllers\Report\PayrollReportController::class, 'print'])->name('payroll.print');
        // The standing-questions overview, alongside the five filter-driven tabs.
        Route::get('payroll/overview',              [\App\Http\Controllers\Report\PayrollReportController::class, 'overview'])->name('payroll.overview');
        Route::get('payroll/overview/export/excel', [\App\Http\Controllers\Report\PayrollReportController::class, 'overviewExcel'])->name('payroll.overview.export.excel');
        Route::get('payroll/overview/export/pdf',   [\App\Http\Controllers\Report\PayrollReportController::class, 'overviewPdf'])->name('payroll.overview.export.pdf');

        Route::get('monthly-profit',       [MonthlyProfitController::class, 'index'])->name('monthly-profit');
        Route::get('monthly-profit/data',  [MonthlyProfitController::class, 'data'])->name('monthly-profit.data');
        Route::get('monthly-profit/print', [MonthlyProfitController::class, 'printView'])->name('monthly-profit.print');

        Route::get('/monthly-attendances', [ReportController::class, 'monthly_attendances'])->name('monthly-attendances');
        Route::get('/attendance/details/{attendance_id}/{date}', [ReportController::class, 'attendanceDetails'])->name('attendance.details');
        
        //task report
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

    //Chat routes
    Route::post('/chat/presign', [\App\Http\Controllers\ChatController::class, 'presignUpload'])->name('chat.presign');
    Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.send');
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
    broadcast(new \App\Events\TestEvent('Hello World'));
    return "Event broadcasted!";
});

//attendance
Route::get('attendance/sync', [ApiAttendanceController::class, 'syncAttendance']);
Route::get('/salary/view/{id}', [EmployeeSalaryController::class, 'view'])
     ->name('salary.view')
     ->middleware('signed'); // এটি লিঙ্কের নিরাপত্তা নিশ্চিত করে
Route::get('/salary/download/{id}', [EmployeeSalaryController::class, 'download'])
    ->name('salary.download')
    ->middleware('signed');

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
