<?php

use App\Http\Controllers\Api\Admin\AccountController;
use App\Http\Controllers\Api\Admin\AdminAttendanceController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;

use App\Http\Controllers\Api\Admin\AdminQuickActionController;
use App\Http\Controllers\Api\Admin\AdminTaskController;
use App\Http\Controllers\Api\Admin\AdminSupportTicketController;
use App\Http\Controllers\Api\Admin\AdvanceSalaryController;
use App\Http\Controllers\Api\Admin\AgentController;
use App\Http\Controllers\Api\Admin\AirportController;
use App\Http\Controllers\Api\Admin\AttendanceController as HrmAttendanceController;
use App\Http\Controllers\Api\Admin\BankController;
use App\Http\Controllers\Api\Admin\BankTransferController;
use App\Http\Controllers\Api\Admin\TicketDepartmentController;

use App\Http\Controllers\Api\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\CountryController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\DesignationController;
use App\Http\Controllers\Api\Admin\EmailMarketingController;
use App\Http\Controllers\Api\Admin\EmployeeSalaryController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\ExpenseController;
use App\Http\Controllers\Api\Admin\ExpenseCategoryController;
use App\Http\Controllers\Api\Admin\ExpenseSubCategoryController;
use App\Http\Controllers\Api\Admin\HolidayController as AdminHolidayController;
use App\Http\Controllers\Api\Admin\LeaveController as AdminLeaveController;
use App\Http\Controllers\Api\Admin\LeaveTypeController;
use App\Http\Controllers\Api\Admin\LoanController;
use App\Http\Controllers\Api\Admin\NoticeController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\SubCategoryController as AdminSubCategoryController;
use App\Http\Controllers\Api\Admin\UnitController as AdminUnitController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\PortalManagementController;
use App\Http\Controllers\Api\Admin\PassportHolderCategoryController;
use App\Http\Controllers\Api\Admin\PassportHolderController;
use App\Http\Controllers\Api\Admin\PayslipController;
use App\Http\Controllers\Api\Admin\PurchaseController;
use App\Http\Controllers\Api\Admin\SaleController;
use App\Http\Controllers\Api\Admin\StateController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Controllers\Api\Admin\TicketPurchaseController;
use App\Http\Controllers\Api\Admin\TicketSaleController;
use App\Http\Controllers\Api\Admin\VisaCategoryController;
use App\Http\Controllers\Api\Admin\VisaProcessingController;
use App\Http\Controllers\Api\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Api\Admin\ResignationController;
use App\Http\Controllers\Api\Admin\SalaryTemplateController;
use App\Http\Controllers\Api\Admin\SmsMarketingController;
use App\Http\Controllers\Api\Admin\StockMovementController;
use App\Http\Controllers\Api\Admin\StockTransferController;
use App\Http\Controllers\Api\Admin\WhatsappMarketingController;
use App\Http\Controllers\Api\Admin\DeviceController;
use App\Http\Controllers\Api\Admin\InvoiceTemplateController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SmsTemplateController;
use App\Http\Controllers\Api\Admin\BoardController;
use App\Http\Controllers\Api\Admin\ColumnController;
use App\Http\Controllers\Api\Admin\ContractManageController;
use App\Http\Controllers\Api\Admin\ContractTypeController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DealManageController;
use App\Http\Controllers\Api\Admin\EstimateController;
use App\Http\Controllers\Api\Admin\JournalController;
use App\Http\Controllers\Api\Admin\LabelController;
use App\Http\Controllers\Api\Admin\LeadFollowupController;
use App\Http\Controllers\Api\Admin\LeadManagerController;
use App\Http\Controllers\Api\Admin\LeadReminderController;
use App\Http\Controllers\Api\Admin\LeadSourceController;
use App\Http\Controllers\Api\Admin\PaymentScheduleController;
use App\Http\Controllers\Api\Admin\ProjectCategoryController;
use App\Http\Controllers\Api\Admin\ProjectManageController;
use App\Http\Controllers\Api\Admin\PromotionController;
use App\Http\Controllers\Api\Admin\ProposalManageController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\SupplierController;
use App\Http\Controllers\Api\Admin\TaskReportController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\Admin\WorkSpaceUserController;

use App\Http\Controllers\Api\Employee\AttendanceController;
use App\Http\Controllers\Api\Employee\AuthController;
use App\Http\Controllers\Api\Employee\DashboardController;
use App\Http\Controllers\Api\Employee\EmployeeProfileController;
use App\Http\Controllers\Api\Employee\EmployeeRequestController;
use App\Http\Controllers\Api\Employee\HolidayController;
use App\Http\Controllers\Api\Employee\InvoiceReportController;
use App\Http\Controllers\Api\Employee\LeaveController;
use App\Http\Controllers\Api\Employee\ManualAttendanceController;
use App\Http\Controllers\Api\Employee\ProjectController;
use App\Http\Controllers\Api\Employee\ResignationController as EmployeeResignationController;
use App\Http\Controllers\Api\Employee\SupportTicketController;
use App\Http\Controllers\Api\Employee\TaskController;
use App\Http\Controllers\Api\Employee\NoticeController as EmployeeNoticeController;
use App\Http\Controllers\Api\Employee\NotificationController as EmployeeNotificationController;
use App\Http\Controllers\Api\Employee\LeadManagerController as EmployeeLeadManagerController;
use App\Http\Controllers\Api\Employee\ChatController;
use App\Http\Controllers\Api\Employee\PushTokenController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// SSO Token Verify (DM Portal থেকে call হবে, public but secret-protected)
Route::get('/sso/verify', [SsoController::class, 'verify']);

// ===== EMPLOYEE ROUTES =====
Route::prefix('employee')->group(function () {
    // Public auth routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Protected employee routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/profile', [EmployeeProfileController::class, 'show']);
        Route::post('/profile', [EmployeeProfileController::class, 'store']);
        Route::put('/profile', [EmployeeProfileController::class, 'update']);

        // Employee resources
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('attendance/report', [AttendanceController::class, 'report']);
        Route::get('attendance/report/download', [AttendanceController::class, 'downloadReport']);
        Route::get('attendance', [AttendanceController::class, 'index']);

        // Manual Attendance
        Route::get('attendance/manual/eligibility', [ManualAttendanceController::class, 'eligibility']);
        Route::get('attendance/manual/today', [ManualAttendanceController::class, 'today']);
        Route::post('attendance/manual/checkin', [ManualAttendanceController::class, 'checkIn']);
        Route::post('attendance/manual/checkout', [ManualAttendanceController::class, 'checkOut']);

        // Resignation
        Route::get('resignation/history', [EmployeeResignationController::class, 'history']);
        Route::post('resignation/apply', [EmployeeResignationController::class, 'apply']);

        // Chat
        Route::get('/chat/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
        Route::get('/chat/employees', [ChatController::class, 'employees'])->name('chat.employees');
        Route::post('/chat/presign', [ChatController::class, 'presignUpload'])->name('chat.presign');
        Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
        Route::get('/chat/fetch/{receiver_id}', [ChatController::class, 'fetchMessages'])->name('chat.fetch');
        Route::post('/chat/read', [ChatController::class, 'markAsRead'])->name('chat.read');
        Route::get('/chat/online-status', [ChatController::class, 'onlineStatus'])->name('chat.online-status');
        Route::put('/chat/message/{chat}', [ChatController::class, 'updateMessage'])->name('chat.message.update');
        Route::delete('/chat/message/{chat}', [ChatController::class, 'destroyMessage'])->name('chat.message.destroy');

        //notifications
        Route::get('notifications', [EmployeeNotificationController::class, 'index'])->name('notifications.index');
        // Route::get('notifications/recent', [EmployeeNotificationController::class, 'recent'])->name('notifications.recent');
        Route::get('notifications/unread-count', [EmployeeNotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/{id}/mark-as-read', [EmployeeNotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::post('notifications/mark-all-read', [EmployeeNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        // Route::delete('notifications/bulk-delete', [EmployeeNotificationController::class, 'bulkDestroy'])->name('notifications.bulk-destroy');
        Route::delete('notifications/{id}', [EmployeeNotificationController::class, 'destroy'])->name('notifications.destroy');

        // Push tokens
        Route::post('/push/token', [PushTokenController::class, 'register'])->name('push.token.register');
        Route::delete('/push/token', [PushTokenController::class, 'unregister'])->name('push.token.unregister');

        // Tasks
        Route::get('tasks/report', [TaskController::class, 'taskReport']);
        Route::apiResource('tasks', TaskController::class);

        // Holidays
        Route::get('holidays', [HolidayController::class, 'index']);

        // Invoice report
        Route::get('invoice-report', [InvoiceReportController::class, 'index']);
        Route::get('invoice-report/download', [InvoiceReportController::class, 'download']);

        // Leaves
        Route::get('leave/types', [LeaveController::class, 'types']);
        Route::get('leave/balance', [LeaveController::class, 'balance']);
        Route::get('leave/consumed', [LeaveController::class, 'consumed']);
        Route::post('leave/apply', [LeaveController::class, 'apply']);
        Route::get('leave/history', [LeaveController::class, 'history']);
        
        // Request
        Route::apiResource('employee-requests', EmployeeRequestController::class)->except('create','edit','show');

        // Support Tickets
        Route::apiResource('support-tickets', SupportTicketController::class);
        Route::post('support-tickets/{support_ticket}/reply', [SupportTicketController::class, 'reply']);

        // Projects
        Route::apiResource('projects', ProjectController::class);

        // Leads
        Route::resource('lead-manager', EmployeeLeadManagerController::class)->except('create','show','edit');
        Route::get('lead-manager/{lead}/status-history', [EmployeeLeadManagerController::class, 'getStatusHistory']);
        Route::get('lead-manager/{lead}/visa-documents', [EmployeeLeadManagerController::class, 'getVisaDocuments']);
        Route::patch('lead-manager/visa-document/{doc}/toggle', [EmployeeLeadManagerController::class, 'toggleVisaDocument']);

        // Office Todos (assigned to employee)
        Route::apiResource('office-todos', \App\Http\Controllers\Api\Employee\OfficeTodoController::class);
        Route::patch('office-todos/{id}/status', [\App\Http\Controllers\Api\Employee\OfficeTodoController::class, 'updateStatus']);
        Route::patch('office-todos/{todo}/checklists/{checklist}/toggle', [\App\Http\Controllers\Api\Employee\OfficeTodoController::class, 'toggleChecklist']);

        // Notices
        Route::get('reminder/notices', [EmployeeNoticeController::class, 'index']);
    });
});


// ===== ADMIN ROUTES =====
Route::prefix('admin')->group(function () {
    // Public auth routes
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password', [AdminAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AdminAuthController::class, 'resetPassword']);

    // Protected admin routes
    Route::middleware('auth:sanctum')->name('admin.')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('attendance/report', [AdminAttendanceController::class, 'report']);
        Route::get('attendance', [AdminAttendanceController::class, 'index']);

        // Manual Attendance Permission
        Route::patch('employees/{userId}/manual-attendance-permission',
        [AdminAttendanceController::class, 'toggleManualPermission'])
        ->name('employees.manual-attendance-permission');

        Route::get('tasks/report', [AdminTaskController::class, 'taskReport']);
        Route::apiResource('tasks', AdminTaskController::class);

        // Projects
        Route::apiResource('projects', ProjectController::class);

        // Office Todos
        Route::apiResource('office-todos', \App\Http\Controllers\Api\Admin\OfficeTodoController::class)->except(['show','edit']);

        // Route::get('office-todos/employees', [\App\Http\Controllers\Api\Admin\OfficeTodoController::class, 'employees']);
        // Route::patch('office-todos/{id}/status', [\App\Http\Controllers\Api\Admin\OfficeTodoController::class, 'updateStatus']);
        // Route::delete('office-todos/destroy', [\App\Http\Controllers\Api\Admin\OfficeTodoController::class, 'destroy']);
        // Route::apiResource('office-todos', \App\Http\Controllers\Api\Admin\OfficeTodoController::class)->except(['destroy']);

        // Quick Actions
        Route::prefix('quick-actions')->name('quick-actions.')->group(function () {
            Route::post('transaction', [AdminQuickActionController::class, 'addTransaction'])->name('transaction');
            Route::post('expense', [AdminQuickActionController::class, 'addExpense'])->name('expense');
            Route::post('notice', [AdminQuickActionController::class, 'postNotice'])->name('notice');
        });

        // Products
        Route::prefix('products')->name('products.')->group(function () {
            Route::apiResource('units', AdminUnitController::class);
            Route::apiResource('brands', AdminBrandController::class);
            Route::apiResource('categories', AdminCategoryController::class);
            Route::apiResource('sub-categories', AdminSubCategoryController::class);
        });
        Route::apiResource('products', AdminProductController::class);

        Route::get('portals/list', [PortalManagementController::class, 'index'])->name('portals.index');
        // Route::apiResource('portal-management', PortalManagementController::class);

        Route::apiResource('vendor', VendorController::class);
        Route::apiResource('agent', AgentController::class);

        Route::get('passport/categories', [PassportHolderCategoryController::class, 'index'])->name('passport-categories.index');
        Route::get('passport/holders', [PassportHolderController::class, 'index'])->name('passport-holders.index');
        Route::get('passport/state', [StateController::class, 'index'])->name('state.index');

        Route::get('visa/categories', [VisaCategoryController::class, 'index'])->name('visa-categories.index');
        Route::get('visa/processing', [VisaProcessingController::class, 'index'])->name('visa-processing.index');

        Route::get('country', [CountryController::class, 'index'])->name('country.index');

        Route::get('tickets/list', [TicketController::class, 'index'])->name('ticket.index');
        Route::get('tickets/purchases', [TicketPurchaseController::class, 'index'])->name('ticket.purchases.index');
        Route::get('tickets/sales', [TicketSaleController::class, 'index'])->name('ticket.sales.index');

        Route::get('airport/list', [AirportController::class, 'index'])->name('airport.index');

        Route::get('inventory/sales', [SaleController::class, 'index'])->name('inventory.sales.index');
        Route::get('inventory/purchases', [PurchaseController::class, 'index'])->name('inventory.purchases.index');
        Route::get('inventory/stock-transfers', [StockTransferController::class, 'index'])->name('inventory.stock-transfers.index');
        Route::get('inventory/stock-movements', [StockMovementController::class, 'index'])->name('inventory.stock-movements.index');

        Route::get('profile', [AdminProfileController::class, 'show']);
        Route::put('profile', [AdminProfileController::class, 'update']);

        Route::get('expense/list', [ExpenseController::class, 'index'])->name('expense.index');
        Route::get('expense/category', [ExpenseCategoryController::class, 'index'])->name('expense.category.index');
        Route::get('expense/sub-categories', [ExpenseSubCategoryController::class, 'index'])->name('expense.sub-categories.index');

        Route::apiResource('banks', BankController::class);
        Route::apiResource('bank_transfers', BankTransferController::class);

        Route::apiResource('accounts', AccountController::class);

        // Payment Schedules
        Route::post('payment-schedules/adhoc', [PaymentScheduleController::class, 'storeAdHoc'])->name('payment-schedules.adhoc');
        Route::resource('payment-schedules', PaymentScheduleController::class)->only(['index', 'store', 'destroy']);
        Route::patch('payment-schedules/{schedule}/cancel',     [PaymentScheduleController::class, 'cancel'])->name('payment-schedules.cancel');
        Route::patch('payment-schedules/{schedule}/approve',    [PaymentScheduleController::class, 'approve'])->name('payment-schedules.approve');
        Route::patch('payment-schedules/{schedule}/reject',     [PaymentScheduleController::class, 'reject'])->name('payment-schedules.reject');
        Route::patch('payment-schedules/{schedule}/reschedule', [PaymentScheduleController::class, 'reschedule'])->name('payment-schedules.reschedule');
        Route::patch('payment-schedules/{schedule}/mark-paid',  [PaymentScheduleController::class, 'markPaid'])->name('payment-schedules.mark-paid');

        

        Route::apiResource('journals', JournalController::class);

        Route::prefix('hrm')->name('hrm.')->group(function () {
            Route::apiResource('designations', DesignationController::class)->except(['create', 'edit']);
            Route::apiResource('departments', DepartmentController::class)->except(['create', 'edit']);
            Route::apiResource('shifts', ShiftController::class)->except(['create', 'edit']);
            Route::apiResource('holidays', AdminHolidayController::class)->except(['create', 'edit']);
            Route::apiResource('leave-types', LeaveTypeController::class)->except(['create', 'edit']);
            Route::apiResource('leaves', AdminLeaveController::class)->except(['create', 'edit']);
            Route::apiResource('resignations', ResignationController::class)->except(['create', 'edit']);
            Route::apiResource('attendances', HrmAttendanceController::class)->except(['create', 'edit']);
        });

        Route::prefix('crm')->name('crm.')->group(function () {
            Route::resource('lead-manager', LeadManagerController::class)->except('create','show','edit');
            Route::get('lead-manager/{lead}/status-history', [LeadManagerController::class, 'getStatusHistory'])->name('lead-manager.status-history');
            Route::get('lead-manager/{lead}/visa-documents', [LeadManagerController::class, 'getVisaDocuments'])->name('lead-manager.visa-documents');
            Route::patch('lead-manager/visa-document/{doc}/toggle', [LeadManagerController::class, 'toggleVisaDocument'])->name('lead-manager.visa-document.toggle');
            
            Route::resource('lead-source', LeadSourceController::class)->except('create','show', 'edit');
            Route::resource('lead-reminder', LeadReminderController::class)->except('create','show', 'edit');
            Route::resource('lead-followup', LeadFollowupController::class)->except('create','show', 'edit');

            Route::apiResource('contracts', ContractManageController::class)->except('create','show', 'edit');
            Route::apiResource('contract-types', ContractTypeController::class)->except('create','show', 'edit');

            Route::resource('deals', DealManageController::class)->except('create','show', 'edit');
            Route::resource('proposals', ProposalManageController::class)->except('create','show', 'edit');
            Route::resource("estimates", EstimateController::class)->except("create","edit");

            // Projects
            Route::apiResource('projects', ProjectManageController::class);
            Route::apiResource('project-categories', ProjectCategoryController::class)->except('create','show', 'edit');
        });

        Route::prefix('payroll')->name('payroll.')->group(function () {
            Route::apiResource('salary-templates', SalaryTemplateController::class)->except(['create', 'edit']);
            Route::apiResource('employee-salaries', EmployeeSalaryController::class)->except(['create', 'edit']);
            Route::apiResource('loans', LoanController::class)->except(['create', 'edit']);
            Route::apiResource('payslips', PayslipController::class)->except(['create', 'edit']);
            Route::apiResource('advance-salaries', AdvanceSalaryController::class)->except(['create', 'edit']);
        });

        Route::prefix('marketing')->name('marketing.')->group(function () {
            Route::apiResource('sms-marketing', SmsMarketingController::class);
            Route::apiResource('email-marketing', EmailMarketingController::class);
            Route::apiResource('whatsapp-marketing', WhatsappMarketingController::class);
        });

        Route::prefix('reminder')->name('reminder.')->group(function () {
            Route::apiResource('support-tickets', AdminSupportTicketController::class);
            Route::post('support-tickets/{support_ticket}/reply', [AdminSupportTicketController::class, 'reply']);
            Route::apiResource('ticket-departments', TicketDepartmentController::class);
            Route::apiResource('notice', NoticeController::class);
            Route::apiResource('notification', NotificationController::class);
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::apiResource('sms-templates', SmsTemplateController::class)->except(['create', 'edit', 'show']);
            Route::apiResource('company-settings', CompanyController::class)->except(['create', 'edit', 'show']);
            Route::apiResource('device-settings', DeviceController::class)->except(['create', 'edit', 'show']);
            Route::apiResource('invoice-templates', InvoiceTemplateController::class)->except(['create', 'edit', 'show']);
            Route::apiResource('role-permissions', RoleController::class)->except(['create', 'edit', 'show']);
        });

        Route::prefix('task')->name('task.')->group(function () {
            Route::apiResource('boards', BoardController::class)->except(['create', 'show']);
            Route::apiResource('columns', ColumnController::class)->except(['create', 'show', 'edit']);
            Route::apiResource('workspace-users', WorkSpaceUserController::class)->except(['create', 'show', 'edit']);
            Route::apiResource('labels', LabelController::class)->except(['create', 'show', 'edit']);
        });

        Route::prefix('report')->name('report.')->group(function () {
            Route::get('/monthly-attendances', [ReportController::class, 'monthly_attendances'])->name('monthly-attendances');
            Route::get('/task-reports', [TaskReportController::class, 'taskReport'])->name('task.reports');
            Route::get('general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
            Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
            Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
            Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('account-ledger', [ReportController::class, 'accountLedger'])->name('account-ledger');
            Route::get('account-statement', [ReportController::class, 'accountStatement'])->name('account-statement');
            Route::get('journal-entries', [ReportController::class, 'journalEntries'])->name('journal-entries');
            Route::get('account-balances', [ReportController::class, 'accountBalances'])->name('account-balances');
        });

        Route::apiResource('users', UserController::class);
        Route::get('user/documents', [UserController::class, 'documents'])->name('user.documents');
        Route::post('user/{user}/documents', [UserController::class, 'updateDocuments'])->name('user.documents.update');

        Route::apiResource('customers', CustomerController::class)->except(['create', 'edit', 'show']);
        Route::apiResource('suppliers', SupplierController::class)->except(['create', 'edit', 'show']);
        Route::apiResource('promotions', PromotionController::class)->except(['create', 'edit', 'show']);
    });
});