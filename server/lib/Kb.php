<?php
declare(strict_types=1);

/* ============================================================
   Kb — what EON knows about the ERP itself, as opposed to the
   numbers inside it.

   Two questions a director actually asks:
     "where do I find the payslips?"      → map()
     "how is the late deduction worked out?" → rule()

   Paths are the ERP's real routes (routes/web.php, prefixed
   with the signed-in role, e.g. /super-admin/payslips).
   ============================================================ */
final class Kb
{
    /** the role slug the boss signs in under */
    public const ROLE = 'super-admin';

    /* ---------------- where things live ----------------
       key => [menu (en), menu (bn), path, what it is (en), what it is (bn), keywords] */
    private static function map(): array
    {
        static $M = null;
        if ($M !== null) return $M;

        $M = [
            /* ---- payroll & people ---- */
            'payroll' => [
                'en_menu' => 'HR → Payroll → Overview', 'bn_menu' => 'এইচআর → পে-রোল → ওভারভিউ',
                'path' => 'payroll/overview',
                'en' => 'the month\'s whole salary run — gross, every deduction, net, and who is still unpaid; it exports to Excel and PDF',
                'bn' => 'পুরো মাসের বেতন হিসাব — গ্রস, সব কর্তন, নিট, আর কার বেতন এখনো বাকি; এক্সেল আর পিডিএফে নামানো যায়',
                'kw' => ['payroll', 'salary run', 'বেতন', 'পে-রোল'],
            ],
            'payslip' => [
                'en_menu' => 'HR → Payslips', 'bn_menu' => 'এইচআর → পে-স্লিপ',
                'path' => 'payslips',
                'en' => 'the individual slips. One person\'s full history is under Payslips → Statement, and a single slip prints from /salary/view/{id}',
                'bn' => 'এক এক জনের স্লিপ। একজনের পুরো ইতিহাস পে-স্লিপ → স্টেটমেন্ট-এ, আর একটা স্লিপ প্রিন্ট হয় /salary/view/{id} থেকে',
                'kw' => ['payslip', 'pay slip', 'salary slip', 'পে-স্লিপ', 'পেস্লিপ', 'বেতন স্লিপ'],
            ],
            'employee_salaries' => [
                'en_menu' => 'HR → Employee Salaries', 'bn_menu' => 'এইচআর → কর্মীর বেতন',
                'path' => 'employee-salaries',
                'en' => 'the salary set per employee, plus the paid/due report',
                'bn' => 'প্রতি কর্মীর নির্ধারিত বেতন, সাথে পরিশোধিত/বকেয়া রিপোর্ট',
                'kw' => ['employee salary', 'salary setup', 'কর্মীর বেতন'],
            ],
            'salary_template' => [
                'en_menu' => 'HR → Salary Templates', 'bn_menu' => 'এইচআর → বেতন টেমপ্লেট',
                'path' => 'salary-templates',
                'en' => 'the allowance/deduction structure applied when a slip is generated',
                'bn' => 'স্লিপ তৈরির সময় যে ভাতা/কর্তনের কাঠামো বসে',
                'kw' => ['salary template', 'বেতন টেমপ্লেট'],
            ],
            'attendance' => [
                'en_menu' => 'HR → Attendances', 'bn_menu' => 'এইচআর → হাজিরা',
                'path' => 'attendances',
                'en' => 'daily punches; the month view is Monthly Attendances, and the device log is Attendance Log',
                'bn' => 'প্রতিদিনের হাজিরা; মাসের হিসাব Monthly Attendances-এ, আর ডিভাইসের লগ Attendance Log-এ',
                'kw' => ['attendance', 'হাজিরা', 'উপস্থিতি'],
            ],
            'shifts' => [
                'en_menu' => 'HR → Shifts / Attendance Settings', 'bn_menu' => 'এইচআর → শিফট / হাজিরা সেটিংস',
                'path' => 'shifts',
                'en' => 'shift start and end — these two times decide late, early-out and overtime for everyone on that shift',
                'bn' => 'শিফটের শুরু আর শেষ — এই দুইটা সময়ই ঠিক করে কে লেট, কে আগে বেরিয়েছে, আর ওভারটাইম কতটুকু',
                'kw' => ['shift', 'শিফট', 'office time'],
            ],
            'leaves' => [
                'en_menu' => 'HR → Leaves', 'bn_menu' => 'এইচআর → ছুটি',
                'path' => 'leaves',
                'en' => 'leave applications and their approval; the entitlements sit under Leave Types',
                'bn' => 'ছুটির আবেদন ও অনুমোদন; কে কত ছুটি পাবে সেটা Leave Types-এ',
                'kw' => ['leave', 'ছুটি'],
            ],
            'holidays' => [
                'en_menu' => 'HR → Holidays', 'bn_menu' => 'এইচআর → ছুটির দিন',
                'path' => 'holidays',
                'en' => 'the public-holiday calendar that marks attendance as holiday instead of absent',
                'bn' => 'সরকারি ছুটির ক্যালেন্ডার — এই দিনগুলো অনুপস্থিত না ধরে ছুটি ধরা হয়',
                'kw' => ['holiday', 'সরকারি ছুটি', 'ছুটির দিন'],
            ],
            'employees' => [
                'en_menu' => 'HR → Users', 'bn_menu' => 'এইচআর → ইউজার',
                'path' => 'user',
                'en' => 'the staff list; each person opens to a summary with payslips, salary transactions and documents',
                'bn' => 'কর্মীদের তালিকা; একজনে ঢুকলে তার সারসংক্ষেপ, পে-স্লিপ, বেতন লেনদেন আর কাগজপত্র',
                'kw' => ['employee', 'staff', 'user', 'কর্মী', 'কর্মচারী'],
            ],
            'departments' => [
                'en_menu' => 'HR → Departments / Designations', 'bn_menu' => 'এইচআর → বিভাগ / পদবি',
                'path' => 'departments',
                'en' => 'the org structure that groups people for reports and expense heads',
                'bn' => 'প্রতিষ্ঠানের কাঠামো — রিপোর্ট আর খরচের খাত এই অনুযায়ী ভাগ হয়',
                'kw' => ['department', 'designation', 'বিভাগ', 'পদবি'],
            ],
            'advance_salary' => [
                'en_menu' => 'HR → Advance Salaries', 'bn_menu' => 'এইচআর → অগ্রিম বেতন',
                'path' => 'advance-salaries',
                'en' => 'advances against salary, with a payment slip and a recovery schedule',
                'bn' => 'বেতনের বিপরীতে অগ্রিম, সাথে পেমেন্ট স্লিপ আর কিস্তিতে কাটার সূচি',
                'kw' => ['advance', 'অগ্রিম', 'এডভান্স'],
            ],
            'loans' => [
                'en_menu' => 'HR → Loans', 'bn_menu' => 'এইচআর → ঋণ',
                'path' => 'loans',
                'en' => 'staff loans with a statement per loan; the EMI comes off the payslip automatically',
                'bn' => 'কর্মীর ঋণ, প্রতিটির আলাদা স্টেটমেন্ট; কিস্তি পে-স্লিপ থেকে নিজে থেকেই কাটে',
                'kw' => ['loan', 'emi', 'ঋণ', 'কিস্তি'],
            ],
            'employee_requests' => [
                'en_menu' => 'HR → Employee Requests', 'bn_menu' => 'এইচআর → কর্মীর আবেদন',
                'path' => 'employee-requests',
                'en' => 'anything staff ask for — money, NOC, equipment — with its own approval chain and a report',
                'bn' => 'কর্মীরা যা চায় — টাকা, এনওসি, সরঞ্জাম — নিজস্ব অনুমোদনের ধাপ আর রিপোর্টসহ',
                'kw' => ['request', 'noc', 'আবেদন'],
            ],
            'resignations' => [
                'en_menu' => 'HR → Resignations', 'bn_menu' => 'এইচআর → পদত্যাগ',
                'path' => 'resignations',
                'en' => 'resignation records and their printout',
                'bn' => 'পদত্যাগের রেকর্ড আর প্রিন্ট',
                'kw' => ['resignation', 'পদত্যাগ', 'leaving'],
            ],

            /* ---- accounts ---- */
            'accounts' => [
                'en_menu' => 'Accounts → Chart of Accounts', 'bn_menu' => 'হিসাব → হিসাব তালিকা',
                'path' => 'accounts',
                'en' => 'every account and its code — 10xx cash, 11xx bank, 13xx receivable, 21xx payable, 4xxx income, 5xxx direct cost, 6xxx–7xxx overhead',
                'bn' => 'সব হিসাব আর তাদের কোড — ১০xx নগদ, ১১xx ব্যাংক, ১৩xx পাওনা, ২১xx দেনা, ৪xxx আয়, ৫xxx সরাসরি খরচ, ৬xxx–৭xxx সাধারণ খরচ',
                'kw' => ['chart of accounts', 'account code', 'হিসাব তালিকা', 'হিসাব কোড'],
            ],
            'ledger' => [
                'en_menu' => 'Accounts → General Ledger / Account Ledger', 'bn_menu' => 'হিসাব → সাধারণ খতিয়ান',
                'path' => 'general-ledger',
                'en' => 'every posting against an account. Account Statement gives the same thing party-wise, and both print',
                'bn' => 'একটা হিসাবের বিপরীতে সব এন্ট্রি। Account Statement একই জিনিস পার্টি অনুযায়ী দেয়, দুটোই প্রিন্ট হয়',
                'kw' => ['ledger', 'general ledger', 'খতিয়ান', 'লেজার'],
            ],
            'journal' => [
                'en_menu' => 'Accounts → Journal Entries', 'bn_menu' => 'হিসাব → জার্নাল এন্ট্রি',
                'path' => 'journal-entries',
                'en' => 'the double entries themselves, each with a voucher and a party voucher',
                'bn' => 'দুতরফা দাখিলাগুলো নিজেই, প্রতিটির ভাউচার আর পার্টি ভাউচারসহ',
                'kw' => ['journal', 'voucher', 'জার্নাল', 'দাখিলা'],
            ],
            'trial_balance' => [
                'en_menu' => 'Accounts → Trial Balance', 'bn_menu' => 'হিসাব → রেওয়ামিল',
                'path' => 'trial-balance',
                'en' => 'debits against credits — if it does not balance, a shared posting account has been tagged to one company',
                'bn' => 'ডেবিট বনাম ক্রেডিট — না মিললে বুঝতে হবে কোনো শেয়ার্ড হিসাব ভুল করে এক কোম্পানিতে ট্যাগ হয়েছে',
                'kw' => ['trial balance', 'রেওয়ামিল'],
            ],
            'balance_sheet' => [
                'en_menu' => 'Accounts → Balance Sheet', 'bn_menu' => 'হিসাব → স্থিতিপত্র',
                'path' => 'balance-sheet',
                'en' => 'assets, liabilities and equity as they stand today',
                'bn' => 'আজকের তারিখে সম্পদ, দায় আর মূলধন',
                'kw' => ['balance sheet', 'স্থিতিপত্র', 'net worth'],
            ],
            'profit_loss' => [
                'en_menu' => 'Accounts → Profit & Loss', 'bn_menu' => 'হিসাব → লাভ-লোকসান',
                'path' => 'profit-loss',
                'en' => 'income less direct cost less overhead for a date range; Monthly Profit shows the same month by month',
                'bn' => 'একটা সময়ের আয় বাদ সরাসরি খরচ বাদ সাধারণ খরচ; Monthly Profit একই জিনিস মাসে মাসে দেখায়',
                'kw' => ['profit', 'p&l', 'লাভ', 'মুনাফা'],
            ],
            'banks' => [
                'en_menu' => 'Accounts → Banks', 'bn_menu' => 'হিসাব → ব্যাংক',
                'path' => 'banks',
                'en' => 'each bank account with its own dashboard and statement; Bank Transfers moves money between them',
                'bn' => 'প্রতিটি ব্যাংক হিসাব, নিজস্ব ড্যাশবোর্ড আর স্টেটমেন্টসহ; Bank Transfers দিয়ে এক ব্যাংক থেকে আরেক ব্যাংকে টাকা যায়',
                'kw' => ['bank', 'ব্যাংক'],
            ],
            'petty_cash' => [
                'en_menu' => 'Accounts → Petty Cash', 'bn_menu' => 'হিসাব → পেটি ক্যাশ',
                'path' => 'petty-cash',
                'en' => 'the float per custodian, its statement, and the daily-fund breakdown per company',
                'bn' => 'প্রতি কাস্টোডিয়ানের ফ্লোট, তার স্টেটমেন্ট, আর কোম্পানিভিত্তিক দৈনিক তহবিলের হিসাব',
                'kw' => ['petty cash', 'float', 'পেটি ক্যাশ'],
            ],
            'expenses' => [
                'en_menu' => 'Accounts → Expenses', 'bn_menu' => 'হিসাব → খরচ',
                'path' => 'expenses',
                'en' => 'every expense with its slip and items; Budget Setup sets the ceilings and Expense Report compares against them',
                'bn' => 'প্রতিটি খরচ, তার স্লিপ আর আইটেমসহ; Budget Setup-এ সীমা বসে আর Expense Report সেটার সাথে মিলিয়ে দেখায়',
                'kw' => ['expense', 'খরচ', 'ব্যয়'],
            ],
            'budget' => [
                'en_menu' => 'Accounts → Expenses → Budget Setup', 'bn_menu' => 'হিসাব → খরচ → বাজেট সেটআপ',
                'path' => 'expenses/budget-setup',
                'en' => 'the monthly ceiling per category — without it EON can report spend but not over-spend',
                'bn' => 'প্রতি খাতের মাসিক সীমা — এটা না থাকলে EON খরচ বলতে পারে, কিন্তু বাজেট ছাড়াল কি না বলতে পারে না',
                'kw' => ['budget', 'বাজেট'],
            ],
            'payment_schedules' => [
                'en_menu' => 'Accounts → Payment Schedules', 'bn_menu' => 'হিসাব → পেমেন্ট সূচি',
                'path' => 'payment-schedules',
                'en' => 'what is due to be paid or received and when — this is where receivables and payables actually live',
                'bn' => 'কবে কী দিতে হবে আর কবে কী পাওয়ার কথা — পাওনা-দেনা আসলে এখানেই থাকে',
                'kw' => ['payment schedule', 'due', 'পেমেন্ট সূচি', 'পাওনা', 'দেনা'],
            ],
            'party_statement' => [
                'en_menu' => 'Accounts → Party Statement', 'bn_menu' => 'হিসাব → পার্টি স্টেটমেন্ট',
                'path' => 'party-statement',
                'en' => 'one customer\'s or supplier\'s full running account, exportable and printable per invoice',
                'bn' => 'এক গ্রাহক বা সরবরাহকারীর পুরো চলতি হিসাব, ইনভয়েসসহ প্রিন্ট ও এক্সপোর্ট করা যায়',
                'kw' => ['party statement', 'পার্টি স্টেটমেন্ট'],
            ],
            'financing' => [
                'en_menu' => 'Accounts → Financing', 'bn_menu' => 'হিসাব → অর্থায়ন',
                'path' => 'financing',
                'en' => 'borrowings — bank loans, party loans, vehicle and credit-card lines',
                'bn' => 'ঋণ — ব্যাংক ঋণ, পার্টি ঋণ, গাড়ি ও ক্রেডিট কার্ড',
                'kw' => ['financing', 'borrowing', 'অর্থায়ন', 'ব্যাংক ঋণ'],
            ],
            'monthly_profit' => [
                'en_menu' => 'Reports → Monthly Profit', 'bn_menu' => 'রিপোর্ট → মাসিক মুনাফা',
                'path' => 'monthly-profit',
                'en' => 'profit month by month, printable — the quickest read on whether the trend is up',
                'bn' => 'মাসে মাসে মুনাফা, প্রিন্টযোগ্য — প্রবণতা উপরে না নিচে, সবচেয়ে দ্রুত এখানেই বোঝা যায়',
                'kw' => ['monthly profit', 'মাসিক মুনাফা'],
            ],

            /* ---- sales / CRM / ops ---- */
            'crm' => [
                'en_menu' => 'CRM → Dashboard / Lead Manager', 'bn_menu' => 'সিআরএম → ড্যাশবোর্ড / লিড ম্যানেজার',
                'path' => 'crm/dashboard',
                'en' => 'leads through their stages, follow-ups and reminders; won interior leads turn into projects',
                'bn' => 'লিড তার ধাপে ধাপে, ফলোআপ আর রিমাইন্ডারসহ; ইন্টেরিয়রের লিড জিতলে সেটা প্রকল্প হয়ে যায়',
                'kw' => ['crm', 'lead', 'pipeline', 'লিড', 'পাইপলাইন'],
            ],
            'customers' => [
                'en_menu' => 'Parties → Customers', 'bn_menu' => 'পার্টি → গ্রাহক',
                'path' => 'customers',
                'en' => 'the customer master, exportable to Excel and PDF',
                'bn' => 'গ্রাহকের তালিকা, এক্সেল আর পিডিএফে নামানো যায়',
                'kw' => ['customer', 'client', 'গ্রাহক'],
            ],
            'suppliers' => [
                'en_menu' => 'Parties → Suppliers / Vendors', 'bn_menu' => 'পার্টি → সরবরাহকারী',
                'path' => 'suppliers',
                'en' => 'the supplier and vendor master',
                'bn' => 'সরবরাহকারী ও ভেন্ডরের তালিকা',
                'kw' => ['supplier', 'vendor', 'সরবরাহকারী'],
            ],
            'projects' => [
                'en_menu' => 'Work → Projects', 'bn_menu' => 'কাজ → প্রকল্প',
                'path' => 'projects',
                'en' => 'projects with budget, team and the boards that hold their tasks',
                'bn' => 'প্রকল্প, তার বাজেট, টিম আর যে বোর্ডে তার কাজগুলো থাকে',
                'kw' => ['project', 'প্রকল্প', 'প্রজেক্ট'],
            ],
            'tasks' => [
                'en_menu' => 'Work → Tasks', 'bn_menu' => 'কাজ → টাস্ক',
                'path' => 'tasks',
                'en' => 'the task boards — columns are the status, and Task Reports exports the lot',
                'bn' => 'টাস্ক বোর্ড — কলামই অবস্থা বোঝায়, আর Task Reports থেকে পুরোটা এক্সপোর্ট হয়',
                'kw' => ['task', 'টাস্ক', 'কাজ'],
            ],
            'office_todos' => [
                'en_menu' => 'Work → Office To-dos', 'bn_menu' => 'কাজ → অফিস কাজের তালিকা',
                'path' => 'office-todos',
                'en' => 'the per-department checklists, separate from project tasks',
                'bn' => 'বিভাগভিত্তিক চেকলিস্ট, প্রকল্পের টাস্ক থেকে আলাদা',
                'kw' => ['todo', 'checklist', 'করণীয়'],
            ],
            'sales' => [
                'en_menu' => 'Sales → Sales / Ticket Sales / Visa Sales', 'bn_menu' => 'বিক্রয় → সেলস / টিকিট / ভিসা',
                'path' => 'sales',
                'en' => 'general sales, plus the travel side: Ticket Sales, Visa Sales, Contract Flight and Contract File sales, each with its own invoice',
                'bn' => 'সাধারণ বিক্রি, সাথে ট্রাভেলের দিক: টিকিট, ভিসা, কন্ট্রাক্ট ফ্লাইট আর কন্ট্রাক্ট ফাইল — প্রতিটির নিজস্ব ইনভয়েস',
                'kw' => ['sale', 'ticket', 'visa', 'বিক্রি', 'টিকিট', 'ভিসা'],
            ],
            'purchases' => [
                'en_menu' => 'Purchase → Purchases', 'bn_menu' => 'ক্রয় → পারচেজ',
                'path' => 'purchases',
                'en' => 'purchases with invoices; Ticket Purchase covers the airline side',
                'bn' => 'ক্রয় ও তার ইনভয়েস; এয়ারলাইন্সের দিকটা Ticket Purchase-এ',
                'kw' => ['purchase', 'ক্রয়', 'পারচেজ'],
            ],
            'notices' => [
                'en_menu' => 'Communication → Notices', 'bn_menu' => 'যোগাযোগ → নোটিশ',
                'path' => 'notices',
                'en' => 'notices to staff; Headline Notices is the banner version',
                'bn' => 'কর্মীদের জন্য নোটিশ; Headline Notices হলো ব্যানার সংস্করণ',
                'kw' => ['notice', 'নোটিশ'],
            ],
        ];
        return $M;
    }

    /* ---------------- how things work ---------------- */
    private static function rules(): array
    {
        static $R = null;
        if ($R !== null) return $R;

        $R = [
            'late_deduction' => [
                'kw' => ['late deduction', 'late rule', 'grace', 'বিলম্ব কর্তন', 'দেরি', 'লেট'],
                'en' => 'Late is counted in minutes against the shift start, and it is forgiven up to two hours a month. '
                      . 'The moment a person\'s late minutes cross 120 in a month, the whole amount is charged — not just the excess — at the per-minute rate. '
                      . 'That rate is the monthly salary divided by the days in the month, divided by 9 hours, divided by 60.',
                'bn' => 'দেরি গোনা হয় শিফট শুরুর সময় থেকে, মিনিটে। মাসে দুই ঘণ্টা পর্যন্ত মাফ। '
                      . 'কিন্তু মাসে মোট দেরি ১২০ মিনিট ছাড়ালেই পুরো সময়টার টাকা কাটে — শুধু বাড়তিটুকু না। '
                      . 'হার হলো: মাসের বেতন ভাগ মাসের দিন, ভাগ ৯ ঘণ্টা, ভাগ ৬০ — এই প্রতি মিনিটের রেট।',
            ],
            'absent_deduction' => [
                'kw' => ['absent deduction', 'absence', 'অনুপস্থিত কর্তন', 'গরহাজির'],
                'en' => 'One absent day costs one day\'s salary — the monthly salary divided by the number of days in that month. Unpaid leave is deducted the same way.',
                'bn' => 'একদিন অনুপস্থিত মানে একদিনের বেতন কাটা — মাসের বেতন ভাগ ওই মাসের দিন সংখ্যা। বেতনহীন ছুটিও একইভাবে কাটে।',
            ],
            'early_out' => [
                'kw' => ['early out', 'early leave', 'আগে চলে', 'আগে বের'],
                'en' => 'Leaving more than ten minutes before the shift ends counts as early-out and is charged at the per-minute rate — unless an approved leave covers that day, in which case it is waived.',
                'bn' => 'শিফট শেষের দশ মিনিটের বেশি আগে বের হলে সেটা early-out ধরা হয় আর প্রতি মিনিটের হারে কাটে — তবে ওই দিনের জন্য অনুমোদিত ছুটি থাকলে মাফ।',
            ],
            'overtime' => [
                'kw' => ['overtime', 'ot', 'ওভারটাইম'],
                'en' => 'Overtime only starts counting sixty minutes after the shift ends, and it is only paid if the person is marked overtime-eligible. Staying late without that flag earns nothing.',
                'bn' => 'শিফট শেষ হওয়ার ষাট মিনিট পর থেকে ওভারটাইম গোনা শুরু, আর টাকা পাবে কেবল যার overtime-eligible চিহ্ন দেওয়া আছে। ওই চিহ্ন ছাড়া দেরি করে থাকলে কিছুই যোগ হয় না।',
            ],
            'payroll_run' => [
                'kw' => ['payroll run', 'salary calculated', 'when is payroll', 'বেতন কিভাবে', 'বেতন হিসাব'],
                'en' => 'Payroll runs automatically on the 1st at 01:00 for the month just finished. Net = gross − absent − unpaid leave − late − early-out − loan EMI − approved advances + overtime. '
                      . 'Note what is not in there: no income tax, no provident fund, no gratuity — the ERP does not compute those.',
                'bn' => 'পে-রোল নিজে থেকেই চলে প্রতি মাসের ১ তারিখ রাত ১টায়, আগের মাসের জন্য। নিট = গ্রস − অনুপস্থিত − বেতনহীন ছুটি − দেরি − early-out − ঋণের কিস্তি − অনুমোদিত অগ্রিম + ওভারটাইম। '
                      . 'যা নেই সেটাও জেনে রাখা দরকার: আয়কর নেই, প্রভিডেন্ট ফান্ড নেই, গ্র্যাচুইটি নেই — ERP এগুলো হিসাব করে না।',
            ],
            'leave_balance' => [
                'kw' => ['leave balance', 'how much leave', 'ছুটি বাকি', 'ছুটির হিসাব'],
                'en' => 'Leave balance is simply the leave type\'s yearly entitlement minus the days already approved this year. Pending applications do not reduce it until they are approved.',
                'bn' => 'ছুটির হিসাব সোজা: ছুটির ধরন অনুযায়ী বছরের বরাদ্দ বাদ এ বছরে অনুমোদিত দিন। আবেদন অনুমোদন না হওয়া পর্যন্ত বরাদ্দ থেকে কাটে না।',
            ],
            'attendance_status' => [
                'kw' => ['attendance status', 'present absent', 'online', 'হাজিরা কিভাবে', 'অনলাইন'],
                'en' => 'Status comes from device punches or a manual selfie check-in: present, absent, leave or holiday. Weekends come from the shift, and Friday–Saturday is the weekend here. '
                      . 'Someone counts as online if they were seen in the last five minutes.',
                'bn' => 'অবস্থা আসে ডিভাইসের পাঞ্চ বা সেলফি চেক-ইন থেকে: উপস্থিত, অনুপস্থিত, ছুটি বা হলিডে। সাপ্তাহিক ছুটি শিফট থেকে আসে, এখানে শুক্র-শনি। '
                      . 'শেষ পাঁচ মিনিটে দেখা গেলে ধরা হয় সে অনলাইন।',
            ],
            'expense_posting' => [
                'kw' => ['expense approval', 'expense posting', 'how is expense', 'খরচ অনুমোদন', 'খরচ পোস্ট'],
                'en' => 'An expense is created pending and posts nothing. On approval it debits the category\'s account (7400 Miscellaneous if the category has none) and credits by how it was paid: '
                      . 'reimburse-to-employee goes to 2240, petty-cash float goes to the float account, bank goes to that bank\'s leaf, and anything else lands on the 1011 petty-cash pool. '
                      . 'A wrong entry is never edited — it is reversed.',
                'bn' => 'খরচ প্রথমে pending হয়ে বসে, কোনো এন্ট্রি হয় না। অনুমোদন হলে খাতের হিসাবে ডেবিট (খাতের হিসাব না থাকলে ৭৪০০ বিবিধ) আর ক্রেডিট যায় কীভাবে দেওয়া হলো সেই অনুযায়ী: '
                      . 'কর্মীকে ফেরত দিলে ২২৪০, পেটি ক্যাশ ফ্লোট হলে ফ্লোটের হিসাব, ব্যাংক হলে ওই ব্যাংকের হিসাব, আর বাকি সব ১০১১ পেটি ক্যাশ পুলে। '
                      . 'ভুল এন্ট্রি কখনো এডিট হয় না — উল্টো এন্ট্রি দিয়ে ঠিক করা হয়।',
            ],
            'salary_posting' => [
                'kw' => ['salary posting', 'salary journal', 'বেতন পোস্ট', 'বেতন এন্ট্রি'],
                'en' => 'Salary debits 6110 Salary Expense. If it is paid it credits the bank leaf; if it is not, it credits 2210 Salaries Payable and opens a payment schedule with the employee as the party.',
                'bn' => 'বেতনে ডেবিট হয় ৬১১০ Salary Expense। পরিশোধ হলে ক্রেডিট যায় ব্যাংকে; না হলে ক্রেডিট ২২১০ Salaries Payable-এ আর কর্মীকে পার্টি ধরে একটা পেমেন্ট সূচি খুলে যায়।',
            ],
            'sale_posting' => [
                'kw' => ['sale posting', 'sales journal', 'বিক্রি এন্ট্রি'],
                'en' => 'A sale debits 1311 Customer Receivable (or the bank if it was cash) and credits a 4xxx income account; the direct cost goes to 5xxx.',
                'bn' => 'বিক্রিতে ডেবিট হয় ১৩১১ Customer Receivable (নগদ হলে ব্যাংক) আর ক্রেডিট যায় ৪xxx আয়ের হিসাবে; সরাসরি খরচ যায় ৫xxx-এ।',
            ],
            'purchase_posting' => [
                'kw' => ['purchase posting', 'ক্রয় এন্ট্রি'],
                'en' => 'A purchase debits 5610 Purchase/COGS or inventory and credits 2111 Supplier Payable, or the bank if it was paid straight away.',
                'bn' => 'ক্রয়ে ডেবিট হয় ৫৬১০ Purchase/COGS বা মজুদ, আর ক্রেডিট ২১১১ Supplier Payable — সাথে সাথে পরিশোধ করলে ব্যাংক।',
            ],
            'payment_workflow' => [
                'kw' => ['payment schedule workflow', 'approve payment', 'পেমেন্ট অনুমোদন'],
                'en' => 'A schedule sits pending until it is approved, rejected or rescheduled — every one of those is logged — and then it is marked paid. Anything past its date is flipped to overdue automatically at 00:05 each day.',
                'bn' => 'একটা সূচি pending থাকে যতক্ষণ না অনুমোদন, বাতিল বা তারিখ বদল হয় — তিনটাই লগে থাকে — তারপর paid চিহ্ন পড়ে। তারিখ পেরোলে প্রতিদিন রাত ১২টা ৫ মিনিটে নিজে থেকেই overdue হয়ে যায়।',
            ],
            'lead_workflow' => [
                'kw' => ['lead workflow', 'lead stage', 'লিডের ধাপ'],
                'en' => 'A lead moves new → contacted → qualified → proposal sent → negotiation → won or lost. The types are air ticket, visa, software, interior and other, and a won interior lead becomes a project.',
                'bn' => 'লিড যায় new → contacted → qualified → proposal sent → negotiation → won বা lost. ধরন পাঁচটা: এয়ার টিকিট, ভিসা, সফটওয়্যার, ইন্টেরিয়র আর অন্যান্য — ইন্টেরিয়রের লিড জিতলে সেটা প্রকল্প হয়ে যায়।',
            ],
            'request_workflow' => [
                'kw' => ['request workflow', 'employee request stage', 'আবেদনের ধাপ'],
                'en' => 'An employee request goes pending → under review → approved or rejected → fulfilled or disbursed (cash, bank, cheque or payroll deduction) → recovered by payslip instalments → closed.',
                'bn' => 'কর্মীর আবেদন যায় pending → under review → approved বা rejected → fulfilled/disbursed (নগদ, ব্যাংক, চেক বা বেতন থেকে কর্তন) → পে-স্লিপের কিস্তিতে আদায় → closed।',
            ],
            'company_scope' => [
                'kw' => ['company id', 'multi company', 'shared account', 'কোম্পানি আলাদা'],
                'en' => 'Twelve companies share one database. A shared posting account must have company_id NULL — reports filter accounts by company but journal items by entry, so tagging a shared account to one company silently breaks that company\'s trial balance.',
                'bn' => 'বারোটা কোম্পানি একই ডাটাবেস ভাগ করে। শেয়ার্ড হিসাবের company_id অবশ্যই NULL থাকতে হবে — রিপোর্ট হিসাব ফিল্টার করে কোম্পানি দিয়ে কিন্তু জার্নাল আইটেম ফিল্টার করে এন্ট্রি দিয়ে, তাই শেয়ার্ড হিসাব এক কোম্পানিতে ট্যাগ করলে ওই কোম্পানির রেওয়ামিল চুপচাপ ভেঙে যায়।',
            ],
            'reversal' => [
                'kw' => ['reversal', 'correction', 'edit entry', 'সংশোধন', 'উল্টো এন্ট্রি'],
                'en' => 'Nothing posted is ever edited. A correction is a reversal entry that points back at the original, so the audit trail stays whole.',
                'bn' => 'পোস্ট হয়ে যাওয়া কিছুই এডিট হয় না। সংশোধন মানে একটা উল্টো এন্ট্রি যেটা মূলটাকে দেখিয়ে দেয়, যাতে অডিট ট্রেইল অক্ষত থাকে।',
            ],
            'missing_features' => [
                'kw' => ['not available', 'missing report', 'reports missing', 'missing', 'aging report', 'cash flow statement', 'vat return', 'what is not', 'যা নেই', 'কি নেই', 'নেই'],
                'en' => 'Four things the ERP does not print yet: receivable/payable aging, a cash-flow statement, an opening-balance screen, and the Bangladesh VAT/TDS returns (Mushak 6.3 and 9.1). '
                      . 'EON computes the aging and the cash position itself, so you are not blind on those two.',
                'bn' => 'ERP এখনো চারটা জিনিস দেয় না: পাওনা-দেনার aging, নগদ প্রবাহের বিবরণী, ওপেনিং ব্যালেন্সের স্ক্রিন, আর বাংলাদেশের ভ্যাট/টিডিএস রিটার্ন (মূসক ৬.৩ ও ৯.১)। '
                      . 'aging আর নগদ অবস্থাটা EON নিজেই বের করে দেয়, ওই দুটোয় আপনি অন্ধ নন।',
            ],
        ];
        return $R;
    }

    /* ---------------- lookup ---------------- */

    /** find the screen that answers a question; $topic is the Nlu intent when it had one */
    public static function findScreen(string $normQuestion, ?string $topic = null): ?array
    {
        $alias = [
            'payroll' => 'payroll', 'payroll_unpaid' => 'payroll', 'overtime' => 'payroll',
            'attendance_today' => 'attendance', 'late_today' => 'attendance', 'chronic_late' => 'attendance',
            'online_now' => 'attendance', 'leaves' => 'leaves', 'holidays' => 'holidays',
            'headcount' => 'employees', 'evaluate_person' => 'employees', 'departments' => 'departments',
            'employee_requests' => 'employee_requests', 'advances' => 'advance_salary', 'loans' => 'loans',
            'cash' => 'banks', 'bank_accounts' => 'banks', 'petty_cash' => 'petty_cash',
            'receivables' => 'payment_schedules', 'payables' => 'payment_schedules',
            'overdue_payments' => 'payment_schedules', 'account_ledger' => 'ledger', 'journal' => 'journal',
            'trial_balance' => 'trial_balance', 'balance_sheet' => 'balance_sheet',
            'profit_loss' => 'profit_loss', 'revenue' => 'sales', 'expenses' => 'expenses',
            'expense_by_category' => 'expenses', 'budget' => 'budget', 'tax' => 'accounts',
            'pipeline' => 'crm', 'customers' => 'customers', 'suppliers' => 'suppliers',
            'projects' => 'projects', 'tasks' => 'tasks', 'todos' => 'office_todos',
            'company_compare' => 'monthly_profit',
        ];

        $M = self::map();
        // an explicit keyword in the sentence beats the intent guess
        $best = null; $bestLen = 0;
        foreach ($M as $key => $row) {
            foreach ($row['kw'] as $kw) {
                if (mb_strpos($normQuestion, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false && mb_strlen($kw) > $bestLen) {
                    $best = $key; $bestLen = mb_strlen($kw);
                }
            }
        }
        if ($best === null && $topic !== null && isset($alias[$topic])) $best = $alias[$topic];
        if ($best === null || !isset($M[$best])) return null;

        $row = $M[$best];
        $row['key'] = $best;
        $row['url'] = '/' . self::ROLE . '/' . $row['path'];
        return $row;
    }

    /** find the rule that answers a "how does it work" question */
    public static function findRule(string $normQuestion, ?string $topic = null): ?array
    {
        $alias = [
            'deduction_rules' => 'late_deduction', 'overtime' => 'overtime', 'payroll' => 'payroll_run',
            'payroll_unpaid' => 'payroll_run', 'leaves' => 'leave_balance',
            'attendance_today' => 'attendance_status', 'late_today' => 'late_deduction',
            'expenses' => 'expense_posting', 'journal' => 'reversal', 'pipeline' => 'lead_workflow',
            'employee_requests' => 'request_workflow', 'receivables' => 'payment_workflow',
            'payables' => 'payment_workflow', 'company_compare' => 'company_scope',
            'tax' => 'missing_features',
        ];

        $R = self::rules();
        $best = null; $bestLen = 0;
        foreach ($R as $key => $row) {
            foreach ($row['kw'] as $kw) {
                if (mb_strpos($normQuestion, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false && mb_strlen($kw) > $bestLen) {
                    $best = $key; $bestLen = mb_strlen($kw);
                }
            }
        }
        if ($best === null && $topic !== null && isset($alias[$topic])) $best = $alias[$topic];
        if ($best === null || !isset($R[$best])) return null;

        $row = $R[$best];
        $row['key'] = $best;
        return $row;
    }

    /** every screen EON can point at — used by the capabilities answer */
    public static function screens(): array
    {
        return array_keys(self::map());
    }
}
