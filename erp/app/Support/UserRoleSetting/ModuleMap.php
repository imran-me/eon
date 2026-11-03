<?php

namespace App\Support\UserRoleSetting;

/**
 * The map User Role Setting gives access on (owner, 2026-11-01): every company, its sections, its
 * modules and each module's pages — taken from the sidebar and the tab bands as they render today.
 * Separate from Spatie's permissions; nothing reads this yet except the settings page.
 *
 * A node: label, icon (Font Awesome), path (under /{role}/, optional), acts (v = view, c = create,
 * e = edit, d = delete; omitted = a group), kids (pages or sub-groups), with (keys this module
 * usually needs alongside — shown as suggestions, never granted by themselves).
 */
final class ModuleMap
{
    private const VIEW = 'v';

    private const FULL = 'vced';

    /**
     * Basic employee access (owner, 2026-11-01): always on and locked for anyone set up here — each
     * person's OWN data, what the employee role gives today, minus "view users" and "lead projects"
     * (those are not own data; tick them on the map). Never stored per person.
     *
     * @return array<int, array{group: string, icon: string, items: array<int, array{0: string, 1: ?string, 2: string}>}>
     */
    public static function basic(): array
    {
        return [
            ['group' => 'Home', 'icon' => 'fas fa-home', 'items' => [
                ['Dashboard', 'dashboard', 'v'], ['My Profile', null, 'v'], ['Notifications', 'notifications', 'v'],
            ]],
            ['group' => 'Attendance', 'icon' => 'fas fa-fingerprint', 'items' => [
                ['My Attendance', 'attendances', 'v'], ['Monthly Attendance Report', 'report/monthly-attendances', 'v'], ['Holidays', 'holidays', 'v'],
            ]],
            ['group' => 'Leave', 'icon' => 'fas fa-umbrella-beach', 'items' => [
                ['My Leaves', 'leaves', 'vc'],
            ]],
            ['group' => 'Salary', 'icon' => 'fas fa-wallet', 'items' => [
                ['My Salary / Payslip', 'employee-salaries', 'v'], ['Salary Template', 'salary-templates', 'v'], ['My Loans', 'loans', 'v'], ['Advance Salary', 'advance-salaries', 'vc'],
            ]],
            ['group' => 'Requests', 'icon' => 'fas fa-inbox', 'items' => [
                ['My Require', 'employee-requests/self-service', 'vc'], ['Resignation', 'resignations', 'vc'],
            ]],
            ['group' => 'Expense', 'icon' => 'fas fa-receipt', 'items' => [
                ['My Expense Claims', 'expenses', 'vc'],
            ]],
            ['group' => 'Tasks', 'icon' => 'fas fa-tasks', 'items' => [
                ['My Tasks', 'tasks', 'vced'], ['Boards', 'boards', 'v'], ['Labels', 'labels', 'v'], ['Task Projects', 'task-projects', 'v'], ['Task Report', 'report/task-reports', 'v'],
            ]],
        ];
    }

    /**
     * What a ticked page lets the code check, as permission subjects ("contract flight" → "view contract
     * flight", "view all contract flight", "create contract flight" …). The longest key prefix wins, so a
     * module's entry covers its pages unless a page has its own. A page with none is not gated by a
     * permission today (super-admin-only or open), so ticking it changes nothing.
     */
    private const SUBJECTS = [
        // Travels
        'travels.ticketing' => ['ticket purchase', 'ticket direct sale'],
        'travels.ticketing.sales' => ['ticket sale'],
        'travels.ticketing.accounts' => ['ticket purchase', 'ticket sale'],
        'travels.ticketing.setup.routes' => ['ticket'],
        'travels.ticketing.setup.airlines' => ['airline'],
        'travels.ticketing.setup.airports' => ['geography'],
        'travels.ticketing.setup.countries' => ['geography'],
        'travels.ticketing.setup.states' => ['geography'],
        'travels.visa' => ['visa'],
        'travels.contract-flight' => ['contract flight', 'flight schedule'],
        'travels.contract-flight.category' => ['flight category'],
        'travels.contract-file' => ['contract file', 'contract file sale'],
        'travels.contract-file.setup' => ['contract file category'],
        'travels.party-statement' => ['party statement'],
        'travels.portals' => ['portal'],
        'travels.passports' => ['passport holder'],
        // Group-wide
        'group.parties.manage.vendors' => ['vendor'],
        'group.parties.manage.agents' => ['agent'],
        'group.parties.manage.customers' => ['customer', 'customers'],
        'group.parties.statement' => ['party statement'],
        'group.parties.import-tx' => ['party transaction import'],
        'group.parties.import-inv' => ['party invoice import'],
        'group.accounts.banks' => ['bank', 'bank account', 'bank transfer'],
        'group.accounts.payment-entry' => ['payment entry', 'payment'],
        'group.accounts.expenses' => ['expense', 'expense category', 'expense sub-category', 'company expense', 'expense from bank'],
        'group.accounts.schedules' => ['payment schedule'],
        'group.accounts.financing' => ['financing'],
        'group.accounts.journals' => ['journal', 'journal entry report'],
        'group.accounts.chart' => ['account'],
        'group.accounts.reports' => ['general ledger report', 'trial balance report', 'profit loss report', 'balance sheet report',
            'account ledger report', 'account statement report', 'account balance report', 'journal entry report'],
        'group.hrm.payroll.salary' => ['salary', 'employee salary'],
        'group.hrm.payroll.payslip' => ['payslip'],
        'group.hrm.payroll.loans' => ['loan'],
        'group.hrm.payroll.advance' => ['advance salary'],
        'group.hrm.payroll.reports' => ['salary', 'report'],
        'group.hrm.payroll.templates' => ['salary template'],
        'group.hrm.departments' => ['department'],
        'group.hrm.designations' => ['designation'],
        'group.hrm.attendance-settings' => ['attendence setting', 'attendance settings'],
        'group.hrm.shifts' => ['shift'],
        'group.hrm.holidays' => ['holiday', 'holidays'],
        'group.hrm.leave-types' => ['leave type', 'leave types'],
        'group.hrm.leaves' => ['leave', 'leaves'],
        'group.hrm.resignations' => ['resignation'],
        'group.hrm.attendances' => ['attendance', 'attendances'],
        'group.users' => ['users', 'company users'],
        'group.business-ops.tracker' => ['operation tracker'],
        'group.business-ops.products.units' => ['unit'],
        'group.business-ops.products.brands' => ['brand'],
        'group.business-ops.products.categories' => ['category'],
        'group.business-ops.products.sub-categories' => ['subcategory'],
        'group.business-ops.products.products' => ['product'],
        'group.business-ops.inventory.customers' => ['customers', 'customer'],
        'group.business-ops.inventory.suppliers' => ['suppliers', 'supplier'],
        'group.business-ops.inventory.sales' => ['sales'],
        'group.business-ops.inventory.purchases' => ['purchases', 'purchase'],
        'group.business-ops.inventory.transfers' => ['stock transfer'],
        'group.business-ops.inventory.adjustments' => ['stock movement'],
        'group.business-ops.inventory.returns' => ['return reference'],
        'group.crm.dashboard' => ['lead manager', 'deal'],
        'group.crm.leads.source' => ['lead source'],
        'group.crm.leads.manager' => ['lead manager'],
        'group.crm.leads.followup' => ['lead followup'],
        'group.crm.leads.reminder' => ['lead reminder'],
        'group.crm.contract-types' => ['contract type', 'contract'],
        'group.crm.contracts' => ['contract'],
        'group.crm.deals' => ['deal'],
        'group.crm.proposals' => ['proposal'],
        'group.crm.estimates' => ['estimate'],
        'group.projects.categories' => ['project category', 'lead project category'],
        'group.projects.projects' => ['project', 'lead project'],
        'group.require' => ['employee request', 'require assignment'],
        'group.tasks.workspace' => ['workspace'],
        'group.tasks.boards' => ['board'],
        'group.tasks.columns' => ['column'],
        'group.tasks.labels' => ['label'],
        'group.tasks.mine' => ['task', 'tasks'],
        'group.tasks.projects' => ['task project'],
        'group.reports.attendance' => ['monthly attendance report'],
        'group.reports.tasks' => ['task report'],
        'group.reminder.departments' => ['ticket department'],
        'group.reminder.support' => ['support ticket'],
        'group.reminder.notices' => ['notice'],
        'group.marketing' => ['marketing'],
        'group.settings.company' => ['company setting'],
        'group.settings.roles' => ['role'],
    ];

    /** @return array<int, string> */
    public static function subjects(string $leafKey): array
    {
        for ($k = $leafKey; $k !== ''; $k = str_contains($k, '.') ? substr($k, 0, strrpos($k, '.')) : '') {
            if (isset(self::SUBJECTS[$k])) {
                return self::SUBJECTS[$k];
            }
        }

        return [];
    }

    /**
     * Basic employee access as permission names: the employee role's own-data permissions today,
     * minus "view users" and the lead projects (not own data).
     *
     * @return array<int, string>
     */
    public static function basicAbilities(): array
    {
        return [
            'view dashboard', 'view attendance', 'view monthly attendance report', 'view holiday',
            'view leave', 'create leave', 'view salary', 'view salary template', 'view loan',
            'view advance salary', 'create advance salary', 'view employee request', 'create employee request',
            'fulfill employee request', 'view resignation', 'create resignation', 'view expense', 'create expense',
            'view task', 'create task', 'edit task', 'delete task', 'view board', 'view label',
            'view task project', 'view task report',
        ];
    }

    /** Shown in this order: the companies (Travels first), Group-wide's shared modules last. */
    private const ORDER = [2, 6, 3, 1, 8, 5, 7, 4];

    /** @return array<int, array<string, mixed>> */
    public static function tree(): array
    {
        $tree = self::all();
        usort($tree, fn ($a, $b) => array_search($a['company_id'], self::ORDER, true) <=> array_search($b['company_id'], self::ORDER, true));

        return $tree;
    }

    /** @return array<int, array<string, mixed>> */
    private static function all(): array
    {
        return [
            self::company(4, 'Group-wide', 'fas fa-sitemap', [
                self::section('Operations', [
                    self::module('group.parties', 'Party Management', 'fas fa-users', null, [
                        self::page('group.parties.manage', 'Manage Parties', 'vendor', self::FULL, [
                            self::page('group.parties.vendors', 'Vendors', 'vendor', self::FULL),
                            self::page('group.parties.agents', 'Agents', 'agent', self::FULL),
                            self::page('group.parties.customers', 'Customers', 'customer', self::FULL),
                        ]),
                        self::page('group.parties.statement', 'Party Statement', 'party-statement', self::VIEW, [
                            self::page('group.parties.statement.dashboard', 'Dashboard', 'party-statement', self::VIEW),
                            self::page('group.parties.statement.agents', 'Agent Ledger', 'party-statement/agents', self::VIEW),
                            self::page('group.parties.statement.vendors', 'Vendor Ledger', 'party-statement/vendors', self::VIEW),
                            self::page('group.parties.statement.customers', 'Customer Ledger', 'party-statement/clients', self::VIEW),
                            self::page('group.parties.statement.company', 'Company Ledger', 'party-statement/company', self::VIEW),
                            self::page('group.parties.statement.activity', 'Activity Report', 'party-statement/activity', self::VIEW),
                        ]),
                        self::page('group.parties.import-tx', 'Import Transactions', 'party-transaction-import?import=transactions', 'vc'),
                        self::page('group.parties.import-inv', 'Import Custom Invoices', 'party-transaction-import?import=invoices', 'vc'),
                    ]),
                ]),
                self::section('Finance & HR', [
                    self::module('group.accounts', 'Accounts', 'fas fa-coins', null, [
                        self::page('group.accounts.banks', 'Manage Banks', 'banks/dashboard', self::FULL, [
                            self::page('group.accounts.banks.dashboard', 'Accounts Dashboard', 'banks/dashboard', self::VIEW),
                            self::page('group.accounts.banks.all', 'All Banks', 'banks', self::FULL),
                            self::page('group.accounts.banks.transfers', 'Bank Transfers', 'bank_transfers', self::FULL),
                        ]),
                        self::page('group.accounts.payment-entry', 'Payment Entry', 'banks/payment-entry', self::FULL),
                        self::page('group.accounts.expenses', 'Expenses', 'expenses', self::FULL, [
                            self::page('group.accounts.expenses.all', 'All Expenses', 'expenses', self::FULL),
                            self::page('group.accounts.expenses.subscriptions', 'Subscriptions', 'expenses/subscriptions', self::FULL),
                            self::page('group.accounts.expenses.classification', 'Classification', 'expense-classification', self::FULL),
                            self::page('group.accounts.expenses.petty-cash', 'Petty Cash', 'petty-cash', self::FULL),
                            self::page('group.accounts.expenses.budget', 'Budget Setup', 'expenses/budget-setup', self::FULL),
                            self::page('group.accounts.expenses.recurring', 'Recurring Expenses', 'expenses/recurring', self::FULL),
                            self::page('group.accounts.expenses.report', 'Expense Report', 'expenses/report', self::VIEW),
                        ]),
                        self::page('group.accounts.schedules', 'Payment Schedules', 'payment-schedules', self::FULL),
                        self::page('group.accounts.financing', 'Capital & Financing', 'financing', self::FULL, [
                            self::page('group.accounts.financing.took', 'Loans We Took', 'financing', self::FULL),
                            self::page('group.accounts.financing.gave', 'Loans We Gave', 'financing', self::FULL),
                            self::page('group.accounts.financing.employee', 'Employee Loans', 'financing', self::FULL),
                            self::page('group.accounts.financing.capital', 'Capital & Drawings', 'financing', self::FULL),
                            self::page('group.accounts.financing.liability', 'Liability', 'financing', self::FULL),
                            self::page('group.accounts.financing.categories', 'Categories', 'financing', self::FULL),
                        ]),
                        self::page('group.accounts.journals', 'Manage Journals', 'journals', self::FULL),
                        self::page('group.accounts.chart', 'Chart of Accounts', 'accounts', self::FULL),
                        self::page('group.accounts.reports', 'Account Reports', 'report/general-ledger', self::VIEW),
                    ]),
                    self::module('group.hrm', 'HRM', 'fas fa-user-tie', null, [
                        self::page('group.hrm.payroll', 'Payroll', 'employee-salaries', self::FULL, [
                            self::page('group.hrm.payroll.salary', 'Salary Manage', 'employee-salaries', self::FULL),
                            self::page('group.hrm.payroll.payslip', 'Payslip', 'payslips', self::FULL),
                            self::page('group.hrm.payroll.loans', 'Loan Management', 'loans', self::FULL),
                            self::page('group.hrm.payroll.advance', 'Advance Salary', 'advance-salaries', self::FULL),
                            self::page('group.hrm.payroll.reports', 'Payroll Reports', 'report/payroll/overview', self::VIEW),
                            self::page('group.hrm.payroll.templates', 'Salary Template', 'salary-templates', self::FULL),
                        ]),
                        self::page('group.hrm.departments', 'Department', 'departments', self::FULL),
                        self::page('group.hrm.designations', 'Designation', 'designations', self::FULL),
                        self::page('group.hrm.attendance-settings', 'Attendance Settings', 'attendence-settings', self::FULL),
                        self::page('group.hrm.shifts', 'Shift', 'shifts', self::FULL),
                        self::page('group.hrm.holidays', 'Holidays', 'holidays', self::FULL),
                        self::page('group.hrm.leave-types', 'Leave Types', 'leave-types', self::FULL),
                        self::page('group.hrm.leaves', 'All Leaves', 'leaves', self::FULL),
                        self::page('group.hrm.resignations', 'Resignations', 'resignations', self::FULL),
                        self::page('group.hrm.attendances', 'All Attendances', 'attendances', self::FULL),
                    ]),
                ]),
                self::section('Business', [
                    self::module('group.users', 'User Management', 'fas fa-user-friends', null, [
                        self::page('group.users.add', 'Add User', 'user/create', 'c'),
                        self::page('group.users.list', 'Users (every role tab)', 'user', self::FULL),
                        self::page('group.users.documents', 'Employee Documents', 'user/documents', self::FULL),
                        self::page('group.users.promotions', 'User Promotions', 'promotions', self::FULL),
                    ]),
                    self::module('group.business-ops', 'Business Operations', 'fas fa-briefcase', null, [
                        self::page('group.business-ops.tracker', 'Status Tracker', 'operation-status-tracker', self::FULL),
                        self::page('group.business-ops.products', 'Products', 'products', self::FULL, [
                            self::page('group.business-ops.products.units', 'Unit', 'units', self::FULL),
                            self::page('group.business-ops.products.brands', 'Brand', 'brands', self::FULL),
                            self::page('group.business-ops.products.categories', 'Category', 'categories', self::FULL),
                            self::page('group.business-ops.products.sub-categories', 'Sub Category', 'sub-categories', self::FULL),
                            self::page('group.business-ops.products.products', 'Product', 'products', self::FULL),
                        ]),
                        self::page('group.business-ops.inventory', 'Inventory', 'sales', self::FULL, [
                            self::page('group.business-ops.inventory.customers', 'Customers', 'customers', self::FULL),
                            self::page('group.business-ops.inventory.suppliers', 'Suppliers', 'suppliers', self::FULL),
                            self::page('group.business-ops.inventory.sales', 'Sale', 'sales', self::FULL),
                            self::page('group.business-ops.inventory.purchases', 'Purchase', 'purchases', self::FULL),
                            self::page('group.business-ops.inventory.transfers', 'Stock Transfer', 'stock-transfers', self::FULL),
                            self::page('group.business-ops.inventory.adjustments', 'Stock Adjustment', 'stock-movements', self::FULL),
                            self::page('group.business-ops.inventory.returns', 'Return Reference', 'return-refs', self::FULL),
                        ]),
                    ]),
                ]),
                self::section('Work', [
                    self::module('group.crm', 'CRM', 'fas fa-handshake', null, [
                        self::page('group.crm.dashboard', 'CRM Dashboard', 'crm/dashboard', self::VIEW),
                        self::page('group.crm.leads', 'Lead', 'lead-manager', self::FULL, [
                            self::page('group.crm.leads.source', 'Lead Source', 'lead-source', self::FULL),
                            self::page('group.crm.leads.manager', 'Lead Manager', 'lead-manager', self::FULL),
                            self::page('group.crm.leads.followup', 'Lead Followup', 'lead-followup', self::FULL),
                            self::page('group.crm.leads.reminder', 'Lead Reminder', 'lead-reminders', self::FULL),
                        ]),
                        self::page('group.crm.contract-types', 'Contract Type', 'contract-types', self::FULL),
                        self::page('group.crm.contracts', 'Contracts', 'contracts', self::FULL),
                        self::page('group.crm.deals', 'Deal Manager', 'deals', self::FULL),
                        self::page('group.crm.proposals', 'Proposal Manager', 'proposals', self::FULL),
                        self::page('group.crm.estimates', 'Estimate', 'estimates', self::FULL),
                    ]),
                    self::module('group.projects', 'Project', 'fas fa-project-diagram', null, [
                        self::page('group.projects.categories', 'Project Category', 'project-categories', self::FULL),
                        self::page('group.projects.projects', 'Projects', 'projects', self::FULL),
                    ]),
                    self::module('group.require', 'Require', 'fas fa-inbox', null, [
                        self::page('group.require.types', 'Require Type', 'require-types', self::FULL),
                        self::page('group.require.all', 'All Require', 'employee-requests', self::FULL),
                        self::page('group.require.mine', 'My Require', 'employee-requests/self-service', 'vc'),
                        self::page('group.require.report', 'Report', 'employee-requests/report', self::VIEW),
                    ]),
                    self::module('group.tasks', 'Task', 'fas fa-tasks', null, [
                        self::page('group.tasks.workspace', 'Workspace', 'workspace-users', self::FULL),
                        self::page('group.tasks.boards', 'Board', 'boards', self::FULL),
                        self::page('group.tasks.columns', 'Column', 'columns', self::FULL),
                        self::page('group.tasks.labels', 'Label', 'labels', self::FULL),
                        self::page('group.tasks.mine', 'My Tasks', 'tasks', self::FULL),
                        self::page('group.tasks.projects', 'Task Projects', 'task-projects', self::FULL),
                        self::page('group.tasks.oversight', 'Employee Task Oversight', 'task-oversight', 've'),
                        self::page('group.tasks.eon', 'EON Intelligence', 'eon', self::VIEW),
                    ]),
                    self::module('group.reports', 'Report', 'fas fa-chart-bar', null, [
                        self::page('group.reports.attendance', 'Monthly Attendance', 'report/monthly-attendances', self::VIEW),
                        self::page('group.reports.tasks', 'Employee Task Report', 'report/task-reports', self::VIEW),
                    ]),
                ]),
                self::section('Communications', [
                    self::module('group.reminder', 'Reminder', 'fas fa-bell', null, [
                        self::page('group.reminder.departments', 'Ticket Department', 'ticket-departments', self::FULL),
                        self::page('group.reminder.support', 'Support Ticket', 'support-tickets', self::FULL),
                        self::page('group.reminder.notices', 'Notices', 'notices', self::FULL),
                        self::page('group.reminder.notifications', 'Notifications', 'notifications', self::VIEW),
                    ]),
                    self::module('group.todos', 'Office Todos', 'fas fa-clipboard-check', 'office-todos', [], self::FULL),
                    self::module('group.marketing', 'Marketing', 'fas fa-bullhorn', null, [
                        self::page('group.marketing.email', 'Email Marketing', 'email-marketing', self::FULL),
                        self::page('group.marketing.sms', 'SMS Marketing', 'sms-marketing', self::FULL),
                        self::page('group.marketing.whatsapp', 'WhatsApp Marketing', 'whatsapp-marketing', self::FULL),
                    ]),
                ]),
                self::section('Settings', [
                    self::module('group.settings', 'Settings', 'fas fa-cog', null, [
                        self::page('group.settings.sms', 'SMS Templates', 'sms_templates', self::FULL),
                        self::page('group.settings.site', 'Site Settings', 'site-settings', 've'),
                        self::page('group.settings.company', 'Company Settings', 'company-settings', self::FULL),
                        self::page('group.settings.device', 'Device Settings', 'device-settings', self::FULL),
                        self::page('group.settings.invoice', 'Invoice Settings', 'invoice-templates', self::FULL),
                        self::page('group.settings.roles', 'Role Settings', 'role-permission', self::FULL),
                        self::page('group.settings.demo', 'Demo Data', 'demo-data', self::FULL),
                        self::page('group.settings.eon-ai', 'EON Connection', 'eon-ai/connection', 've'),
                    ]),
                    self::module('group.trash', 'Trash', 'fas fa-trash-restore', 'trash', [], 'vd'),
                ]),
            ]),

            self::company(2, null, 'fas fa-plane', [
                self::section('Overview', [
                    self::module('travels.dashboard', 'Company Dashboard', 'fas fa-building', 'company/2/dashboard?company=2', [], self::VIEW),
                ]),
                self::section('Services', [
                    self::module('travels.ticketing', 'Ticketing', 'fas fa-ticket-alt', 'ticketing/dashboard?company=2', [
                        self::page('travels.ticketing.dashboard', 'Ticket Dashboard', 'ticketing/dashboard?company=2', self::VIEW),
                        self::page('travels.ticketing.orders', 'Ticket Orders', 'ticket-purchase?company=2', self::FULL),
                        self::page('travels.ticketing.import', 'Ticket Import', 'ticket-direct-sale/create?company=2', 'vc'),
                        self::page('travels.ticketing.services', 'Ticket Services', 'ticket-direct-sale/create?company=2', 'vc'),
                        self::page('travels.ticketing.sales', 'Manage Sales', 'ticket-sales?company=2', self::FULL),
                        self::page('travels.ticketing.accounts', 'Ticket Accounts', 'ticketing/accounts?company=2', self::VIEW),
                        self::page('travels.ticketing.setup', 'Ticket Setup', 'tickets?company=2', self::FULL, [
                            self::page('travels.ticketing.setup.routes', 'Ticket Manage (routes)', 'tickets?company=2', self::FULL),
                            self::page('travels.ticketing.setup.airlines', 'Airlines', 'airlines?company=2', self::FULL),
                            self::page('travels.ticketing.setup.airports', 'Airport Management', 'airport?company=2', self::FULL),
                            self::page('travels.ticketing.setup.countries', 'Country', 'countries?company=2', self::FULL),
                            self::page('travels.ticketing.setup.states', 'States', 'states?company=2', self::FULL),
                        ]),
                    ], null, ['travels.passports', 'travels.portals', 'travels.party-statement']),
                    self::module('travels.visa', 'Visa Processing', 'fas fa-passport', 'visa-dashboard?company=2', [
                        self::page('travels.visa.dashboard', 'Visa Dashboard', 'visa-dashboard?company=2', self::VIEW),
                        self::page('travels.visa.board', 'Application Board', 'visa?company=2', self::FULL),
                        self::page('travels.visa.sales', 'Manage Sales', 'visa-sales?company=2', self::FULL),
                        self::page('travels.visa.other', 'Other Services', 'other-visa-services?company=2', self::FULL),
                        self::page('travels.visa.accounts', 'Visa Accounts', 'visa-accounts?company=2', self::VIEW),
                        self::page('travels.visa.setup', 'Visa Setup', 'visa-category?company=2', self::FULL, [
                            self::page('travels.visa.setup.categories', 'Visa Category', 'visa-category?company=2', self::FULL),
                            self::page('travels.visa.setup.service-types', 'Service Types', 'other-service-types?company=2', self::FULL),
                        ]),
                    ], null, ['travels.passports', 'travels.party-statement']),
                    self::module('travels.contract-flight', 'Contract Flight', 'fas fa-file-signature', 'contract-flights/dashboard?company=2', [
                        self::page('travels.contract-flight.dashboard', 'Flight Dashboard', 'contract-flights/dashboard?company=2', self::VIEW),
                        self::page('travels.contract-flight.flights', 'Flights & Passengers', 'contract-flights?company=2', self::FULL),
                        self::page('travels.contract-flight.board', 'Passenger Board', 'contract-flight-board?company=2', 've'),
                        self::page('travels.contract-flight.accounts', 'Flight Accounts', 'contract-flights/accounts?company=2', self::VIEW),
                        self::page('travels.contract-flight.messages', 'Messages', 'contract-flights/messages?company=2', 'vc'),
                        self::page('travels.contract-flight.category', 'Flight Category', 'flight-categories?company=2', self::FULL, [
                            self::page('travels.contract-flight.category.categories', 'Flight Category', 'flight-categories?company=2', self::FULL),
                            self::page('travels.contract-flight.category.types', 'Category Type', 'flight-category-types?company=2', self::FULL),
                            self::page('travels.contract-flight.category.presets', 'Price Presets', 'flight-price-presets?company=2', self::FULL),
                            self::page('travels.contract-flight.category.officers', 'Officers', 'flight-officers?company=2', self::FULL),
                        ]),
                    ], null, ['travels.ticketing', 'travels.passports', 'travels.party-statement', 'group.parties']),
                    self::module('travels.contract-file', 'Contract File', 'fas fa-folder', 'contract-files/dashboard?company=2', [
                        self::page('travels.contract-file.dashboard', 'File Dashboard', 'contract-files/dashboard?company=2', self::VIEW),
                        self::page('travels.contract-file.files', 'Files & Passengers', 'contract-files?company=2', self::FULL),
                        self::page('travels.contract-file.board', 'Passenger Board', 'contract-files/board?company=2', 've'),
                        self::page('travels.contract-file.accounts', 'File Accounts', 'contract-files/accounts?company=2', self::VIEW),
                        self::page('travels.contract-file.setup', 'Setup', 'contract-file-setup?company=2', self::FULL, [
                            self::page('travels.contract-file.setup.requirements', 'Requirements', 'contract-file-setup?company=2', self::FULL),
                            self::page('travels.contract-file.setup.presets', 'Price Presets', 'contract-file-setup?company=2', self::FULL),
                            self::page('travels.contract-file.setup.visa-types', 'Visa Types', 'contract-file-setup?company=2', self::FULL),
                            self::page('travels.contract-file.setup.jobs', 'Job Categories', 'contract-file-categories?company=2', self::FULL),
                        ]),
                    ], null, ['travels.passports', 'travels.party-statement']),
                ]),
                self::section('Operations', [
                    self::module('travels.party-statement', 'Party Statement (Travels)', 'fas fa-book', 'party-statement?company=2', [], self::VIEW),
                    self::module('travels.portals', 'Portal Management', 'fas fa-globe', 'portal-management?company=2', [
                        self::page('travels.portals.add', 'Add Portal', 'portal-management/create?company=2', 'c'),
                        self::page('travels.portals.manage', 'Manage Portal', 'portal-management?company=2', self::FULL),
                    ]),
                    self::module('travels.passports', 'Passport Management', 'fas fa-id-card', 'passport-holder?company=2', [
                        self::page('travels.passports.categories', 'Passport Category', 'passport-holder-category?company=2', self::FULL),
                        self::page('travels.passports.holders', 'Passport Holder', 'passport-holder?company=2', self::FULL),
                    ]),
                ]),
            ]),

            self::company(6, null, 'fas fa-couch', [
                self::section('Overview', [
                    self::module('woodart.dashboard', 'Company Dashboard', 'fas fa-building', 'company/6/dashboard?company=6', [], self::VIEW),
                    self::module('woodart.overview', 'Dashboard', 'fas fa-chart-pie', 'woodart/dashboard/overview?company=6', [], self::VIEW),
                ]),
                self::section('Projects & Design', [
                    self::module('woodart.leads', 'Leads & CRM', 'fas fa-funnel-dollar', null, [], self::FULL, [], true),
                    self::module('woodart.projects', 'Projects', 'fas fa-drafting-compass', 'woodart/projects/active?company=6', [
                        self::page('woodart.projects.active', 'Active Projects', 'woodart/projects/active?company=6', self::FULL),
                        self::page('woodart.projects.design', 'Design Studio', 'woodart/projects/design?company=6', self::FULL),
                        self::page('woodart.projects.milestones', 'Milestones', 'woodart/projects/milestones?company=6', self::FULL),
                        self::page('woodart.projects.gallery', 'Gallery', 'woodart/projects/gallery?company=6', self::FULL),
                    ]),
                    self::module('woodart.scope', 'Spaces & Phases', 'fas fa-layer-group', 'woodart/scope/spaces?company=6', [
                        self::page('woodart.scope.spaces', 'Spaces', 'woodart/scope/spaces?company=6', self::FULL),
                        self::page('woodart.scope.phases', 'Phase Board', 'woodart/scope/phases?company=6', self::FULL),
                        self::page('woodart.scope.materials', 'Material Demand', 'woodart/scope/materials?company=6', self::VIEW),
                        self::page('woodart.scope.load', 'Team Load', 'woodart/scope/load?company=6', self::VIEW),
                    ]),
                    self::module('woodart.design', 'Design & 3D', 'fas fa-cube', 'woodart/design/register?company=6', [
                        self::page('woodart.design.register', 'Drawing Register', 'woodart/design/register?company=6', self::FULL),
                        self::page('woodart.design.approvals', 'Approvals', 'woodart/design/approvals?company=6', 've'),
                        self::page('woodart.design.load', 'Design Load', 'woodart/design/load?company=6', self::VIEW),
                    ]),
                    self::module('woodart.estimates', 'Estimates & BOQ', 'fas fa-file-invoice-dollar', 'woodart/estimates/quotations?company=6', [
                        self::page('woodart.estimates.quotations', 'Quotations', 'woodart/estimates/quotations?company=6', self::FULL),
                        self::page('woodart.estimates.boq', 'Bill of Materials', 'woodart/estimates/boq?company=6', self::FULL),
                        self::page('woodart.estimates.costing', 'Costing', 'woodart/estimates/costing?company=6', self::VIEW),
                    ]),
                    self::module('woodart.clients', 'Clients', 'fas fa-address-book', 'woodart/clients/directory?company=6', [
                        self::page('woodart.clients.directory', 'Directory', 'woodart/clients/directory?company=6', self::FULL),
                        self::page('woodart.clients.portfolio', 'Portfolio', 'woodart/clients/portfolio?company=6', self::VIEW),
                        self::page('woodart.clients.segments', 'Segments', 'woodart/clients/segments?company=6', self::VIEW),
                    ]),
                ]),
                self::section('Production & Site', [
                    self::module('woodart.materials', 'Materials', 'fas fa-boxes', 'woodart/materials/stock?company=6', [
                        self::page('woodart.materials.stock', 'Stock', 'woodart/materials/stock?company=6', self::FULL),
                        self::page('woodart.materials.movements', 'Movements', 'woodart/materials/movements?company=6', self::FULL),
                        self::page('woodart.materials.reorder', 'Reorder', 'woodart/materials/reorder?company=6', 've'),
                        self::page('woodart.materials.valuation', 'Valuation', 'woodart/materials/valuation?company=6', self::VIEW),
                    ]),
                    self::module('woodart.workshop', 'Workshop', 'fas fa-hammer', 'woodart/production/jobs?company=6', [
                        self::page('woodart.workshop.jobs', 'Job Register', 'woodart/production/jobs?company=6', self::FULL),
                        self::page('woodart.workshop.board', 'Workshop Board', 'woodart/production/board?company=6', 've'),
                        self::page('woodart.workshop.load', 'Station Load', 'woodart/production/load?company=6', self::VIEW),
                    ]),
                    self::module('woodart.install', 'Site & Install', 'fas fa-tools', 'woodart/installation/schedule?company=6', [
                        self::page('woodart.install.schedule', 'Schedule', 'woodart/installation/schedule?company=6', self::FULL),
                        self::page('woodart.install.snags', 'Snag List', 'woodart/installation/snags?company=6', self::FULL),
                        self::page('woodart.install.teams', 'Teams', 'woodart/installation/teams?company=6', self::FULL),
                    ]),
                    self::module('woodart.procurement', 'Procurement', 'fas fa-truck-loading', 'woodart/procurement/orders?company=6', [
                        self::page('woodart.procurement.orders', 'Purchase Orders', 'woodart/procurement/orders?company=6', self::FULL),
                        self::page('woodart.procurement.vendors', 'Vendors', 'woodart/procurement/vendors?company=6', self::FULL),
                        self::page('woodart.procurement.spend', 'Spend', 'woodart/procurement/spend?company=6', self::VIEW),
                    ]),
                ]),
                self::section('Money', [
                    self::module('woodart.accounts', 'Project Accounts', 'fas fa-calculator', 'woodart/accounts/overview?company=6', [
                        self::page('woodart.accounts.overview', 'Overview', 'woodart/accounts/overview?company=6', self::VIEW),
                        self::page('woodart.accounts.expenses', 'Expenses', 'woodart/accounts/expenses?company=6', self::FULL),
                        self::page('woodart.accounts.payables', 'Payables', 'woodart/accounts/payables?company=6', self::FULL),
                        self::page('woodart.accounts.pnl', 'Project Cost', 'woodart/accounts/pnl?company=6', self::VIEW),
                        self::page('woodart.accounts.parties', 'Vendors & Clients', 'woodart/accounts/parties?company=6', self::VIEW),
                    ]),
                    self::module('woodart.payroll', 'Payroll', 'fas fa-money-check-alt', null, [], self::FULL, [], true),
                    self::module('woodart.ledgers', 'Ledgers', 'fas fa-book-open', null, [], self::VIEW, [], true),
                    self::module('woodart.hrm', 'HRM', 'fas fa-user-tie', null, [], self::FULL, [], true),
                    self::module('woodart.reports', 'Reports', 'fas fa-chart-line', null, [], self::VIEW, [], true),
                    self::module('woodart.analytics', 'Analytics', 'fas fa-chart-area', null, [], self::VIEW, [], true),
                    self::module('woodart.tasks', 'My Tasks', 'fas fa-check-square', null, [], self::FULL, [], true),
                    self::module('woodart.settings', 'Settings', 'fas fa-sliders-h', null, [], self::FULL, [], true),
                ]),
            ]),

            self::company(3, null, 'fas fa-hard-hat', [
                self::section('Overview', [
                    self::module('construction.dashboard', 'Company Dashboard', 'fas fa-building', 'company/3/dashboard', [], self::VIEW),
                ]),
                self::section('Money', [
                    self::module('construction.accounts', 'Project Accounts', 'fas fa-calculator', 'construction/accounts/projects', [], self::FULL),
                    self::module('construction.party-statement', 'Party Statement (Constructions)', 'fas fa-book', 'party-statement/vendors?company=3', [], self::VIEW),
                ]),
            ]),

            self::company(1, null, 'fas fa-microchip', [
                self::section('Overview', [self::module('it.dashboard', 'Company Dashboard', 'fas fa-building', 'company/1/dashboard', [], self::VIEW)]),
            ]),
            self::company(8, null, 'fas fa-city', [
                self::section('Overview', [self::module('properties.dashboard', 'Company Dashboard', 'fas fa-building', 'company/8/dashboard', [], self::VIEW)]),
            ]),
            self::company(5, null, 'fas fa-store', [
                self::section('Overview', [self::module('shop.dashboard', 'Company Dashboard', 'fas fa-building', 'company/5/dashboard', [], self::VIEW)]),
            ]),
            self::company(7, null, 'fas fa-industry', [
                self::section('Overview', [self::module('manufacturing.dashboard', 'Company Dashboard', 'fas fa-building', 'company/7/dashboard', [], self::VIEW)]),
            ]),
        ];
    }

    /**
     * Every page that carries actions: key => [letters it allows, its module's key].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function leaves(): array
    {
        $out = [];
        $walk = function (array $n, ?string $module) use (&$walk, &$out) {
            if (($n['type'] ?? '') === 'module') {
                $module = $n['key'];
            }
            if (! empty($n['acts']) && empty($n['kids'])) {
                $out[$n['key']] = [$n['acts'], (string) $module];
            }
            foreach ($n['kids'] ?? [] as $k) {
                $walk($k, $module);
            }
        };
        foreach (self::all() as $c) {
            $walk($c, null);
        }

        return $out;
    }

    /**
     * Every module: key => [label, company id].
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function modules(): array
    {
        $out = [];
        foreach (self::all() as $c) {
            foreach ($c['kids'] as $section) {
                foreach ($section['kids'] as $m) {
                    $out[$m['key']] = [$m['label'], (int) $c['company_id']];
                }
            }
        }

        return $out;
    }

    /** @param array<int, array<string, mixed>> $sections */
    private static function company(int $id, ?string $label, string $icon, array $sections): array
    {
        return ['type' => 'company', 'company_id' => $id, 'label' => $label, 'icon' => $icon, 'kids' => $sections];
    }

    /** @param array<int, array<string, mixed>> $modules */
    private static function section(string $label, array $modules): array
    {
        return ['type' => 'section', 'label' => $label, 'kids' => $modules];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, string>  $with
     */
    private static function module(string $key, string $label, string $icon, ?string $path, array $pages, ?string $acts = null, array $with = [], bool $soon = false): array
    {
        return ['type' => 'module', 'key' => $key, 'label' => $label, 'icon' => $icon, 'path' => $path,
            'acts' => $pages ? null : ($acts ?? self::VIEW), 'kids' => $pages, 'with' => $with, 'soon' => $soon];
    }

    /** @param array<int, array<string, mixed>> $kids */
    private static function page(string $key, string $label, ?string $path, string $acts, array $kids = []): array
    {
        return ['type' => 'page', 'key' => $key, 'label' => $label, 'path' => $path, 'acts' => $kids ? null : $acts, 'kids' => $kids];
    }
}
