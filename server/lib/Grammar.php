<?php
declare(strict_types=1);

/* ============================================================
   Grammar — EON composes instead of enumerating.

   A boss does not ask a fixed list of questions. He takes a VERB
   ("how much", "show", "compare", "rank", "where is", "remind"),
   points it at a SUBJECT (cash, receivable, visa file, payslip,
   a person, a company) and hangs QUALIFIERS off it (this month,
   last month, for Epal Travels, top 5, overdue, per department).

   Writing one intent per phrasing does not scale: with 40 subjects
   and 10 verbs the sentence space is in the thousands, and Bangla,
   English and Banglish each triple it. So Nlu keeps its scored cues
   for the idioms people really say, and falls through to here for
   everything else: decompose the sentence into verb + subject +
   qualifiers, then route that pair to the handler that can answer it.

   The pair is what matters. (count, visa_file) and (total, visa_file)
   both land on service_ops; (rank, employee) lands on staff_ranking
   while (know, employee) lands on evaluate_person. One table, read
   both ways: it routes at runtime, and tools/matrix-run.php walks the
   whole cross-product to prove how much of the space actually answers.
   ============================================================ */
final class Grammar
{
    /* ---------------- verbs: what the boss wants done ---------------- */

    /** id => surface forms per script. Order matters only for readability. */
    public const VERBS = [
        // ask for a figure
        'total'   => ['en' => ['how much', 'what is the total', 'total', 'sum of', 'value of', 'worth'],
                      'bn' => ['কত', 'কত টাকা', 'মোট', 'সর্বমোট', 'পরিমাণ'],
                      'bl' => ['koto', 'koto taka', 'mot', 'total']],
        // ask for a count
        'count'   => ['en' => ['how many', 'number of', 'count of', 'how many are'],
                      'bn' => ['কতগুলো', 'কতজন', 'কয়টা', 'কয়জন', 'সংখ্যা'],
                      'bl' => ['kotogulo', 'kojon', 'koyta', 'koto gula']],
        // ask to see it
        'show'    => ['en' => ['show', 'show me', 'give me', 'list', 'what is', 'what are', 'tell me', 'i want', 'open the list of'],
                      'bn' => ['দেখাও', 'দাও', 'বলো', 'তালিকা', 'জানাও', 'কী অবস্থা'],
                      'bl' => ['dekhao', 'dao', 'bolo', 'talika', 'janao']],
        // ask which is biggest / rank them
        'rank'    => ['en' => ['top', 'biggest', 'largest', 'highest', 'worst', 'lowest', 'best', 'rank', 'who is the most'],
                      'bn' => ['সবচেয়ে বেশি', 'সবচেয়ে বড়', 'সবচেয়ে কম', 'সেরা', 'শীর্ষ', 'সবচেয়ে খারাপ'],
                      'bl' => ['sobcheye beshi', 'sobcheye boro', 'sera', 'top', 'sobcheye kom']],
        // ask to compare
        'compare' => ['en' => ['compare', 'versus', ' vs ', 'against last', 'better than', 'difference between'],
                      'bn' => ['তুলনা', 'তুলনায়', 'চেয়ে', 'পার্থক্য'],
                      'bl' => ['tulona', 'compare', 'cheye']],
        // ask where it is going
        'trend'   => ['en' => ['trend', 'forecast', 'projection', 'next month', 'next quarter', 'going to', 'will we'],
                      'bn' => ['পূর্বাভাস', 'ভবিষ্যৎ', 'আগামী মাসে', 'প্রবণতা'],
                      'bl' => ['forecast', 'agami mase', 'trend']],
        // ask for the rule behind a number
        'explain' => ['en' => ['how is', 'how does', 'why is', 'what is the rule', 'explain', 'how do you calculate', 'on what basis'],
                      'bn' => ['কীভাবে', 'কিভাবে', 'কেন', 'নিয়ম', 'কোন নিয়মে', 'ব্যাখ্যা'],
                      'bl' => ['kivabe', 'keno', 'niyom', 'explain']],
        // ask where the screen is
        'locate'  => ['en' => ['where is', 'where do i', 'which screen', 'which page', 'take me to', 'open'],
                      'bn' => ['কোথায়', 'কোন স্ক্রিনে', 'কোন পাতায়', 'নিয়ে চলো'],
                      'bl' => ['kothay', 'kon screen', 'niye cholo']],
        // tell EON to do something
        'act'     => ['en' => ['remind', 'draft', 'send', 'note that', 'approve', 'reject', 'assign', 'follow up'],
                      'bn' => ['মনে করিয়ে', 'খসড়া', 'পাঠাও', 'অনুমোদন', 'বাতিল', 'দায়িত্ব দাও', 'তাগাদা'],
                      'bl' => ['remind', 'draft', 'pathao', 'onumodon', 'tagada']],
    ];

    /* ---------------- subjects: what the ERP holds ---------------- */

    /**
     * id => [
     *   'en'|'bn'|'bl' => surface forms,
     *   'intent'       => where a plain question about it goes,
     *   'ops'          => the verbs that make sense on it (for the coverage matrix),
     *   'by'           => optional per-verb override of the intent,
     * ]
     */
    public const SUBJECTS = [
        /* --- money on hand --- */
        'cash'          => ['en' => ['cash', 'cash in hand', 'liquidity', 'money we have'], 'bn' => ['ক্যাশ', 'নগদ', 'হাতে টাকা'], 'bl' => ['cash', 'nogod', 'hate taka'],
                            'intent' => 'cash', 'ops' => ['total', 'show', 'compare', 'trend'], 'by' => ['trend' => 'burn_runway', 'compare' => 'company_compare']],
        'bank'          => ['en' => ['bank', 'bank account', 'bank balance', 'banks'], 'bn' => ['ব্যাংক', 'ব্যাংক হিসাব'], 'bl' => ['bank', 'bank account'],
                            'intent' => 'bank_accounts', 'ops' => ['total', 'count', 'show', 'rank']],
        'petty_cash'    => ['en' => ['petty cash', 'float', 'cash float'], 'bn' => ['পেটি ক্যাশ', 'খুচরা নগদ'], 'bl' => ['petty cash'],
                            'intent' => 'petty_cash', 'ops' => ['total', 'show']],
        'runway'        => ['en' => ['runway', 'burn', 'burn rate', 'how long will cash last'], 'bn' => ['কত দিন চলবে', 'খরচের হার'], 'bl' => ['runway', 'koto din cholbe'],
                            'intent' => 'burn_runway', 'ops' => ['total', 'show', 'trend']],

        /* --- what we are owed and owe --- */
        'receivable'    => ['en' => ['receivable', 'receivables', 'who owes us', 'debtors', 'collection'], 'bn' => ['পাওনা', 'আদায়', 'কে টাকা দেবে'], 'bl' => ['pawna', 'ke taka debe'],
                            'intent' => 'receivables', 'ops' => ['total', 'count', 'show', 'rank']],
        'payable'       => ['en' => ['payable', 'payables', 'what we owe', 'creditors', 'bills to pay'], 'bn' => ['দেনা', 'দিতে হবে', 'বকেয়া বিল'], 'bl' => ['dena', 'dite hobe'],
                            'intent' => 'payables', 'ops' => ['total', 'count', 'show', 'rank']],
        'overdue'       => ['en' => ['overdue', 'past due', 'late payment'], 'bn' => ['মেয়াদ পার', 'সময় পার'], 'bl' => ['overdue', 'somoy par'],
                            'intent' => 'overdue_payments', 'ops' => ['total', 'count', 'show']],
        'party_account' => ['en' => ['balance for', 'party statement', 'ledger for', 'account of'], 'bn' => ['পার্টির হিসাব', 'কত দিয়েছে'], 'bl' => ['party statement', 'koto diyeche'],
                            'intent' => 'party_balance', 'ops' => ['total', 'show']],

        /* --- the service business --- */
        'revenue'       => ['en' => ['revenue', 'sales', 'turnover', 'business', 'top line'], 'bn' => ['বিক্রি', 'আয়', 'রাজস্ব'], 'bl' => ['bikri', 'ay', 'revenue'],
                            'intent' => 'revenue', 'ops' => ['total', 'count', 'show', 'compare', 'trend']],
        'visa_file'     => ['en' => ['visa', 'visa file', 'visa processing', 'visa application'], 'bn' => ['ভিসা', 'ভিসা ফাইল', 'ভিসা প্রসেসিং'], 'bl' => ['visa', 'visa file'],
                            'intent' => 'service_ops', 'ops' => ['total', 'count', 'show', 'rank']],
        'ticket'        => ['en' => ['ticket', 'air ticket', 'ticket sale', 'ticket purchase'], 'bn' => ['টিকিট', 'এয়ার টিকিট', 'টিকিট বিক্রি'], 'bl' => ['ticket', 'air ticket'],
                            'intent' => 'ticket_business', 'ops' => ['total', 'count', 'show', 'rank']],
        'portal'        => ['en' => ['portal', 'bsp', 'iata', 'booking portal'], 'bn' => ['পোর্টাল'], 'bl' => ['portal', 'bsp'],
                            'intent' => 'ticket_business', 'ops' => ['total', 'show']],
        'passenger'     => ['en' => ['passport holder', 'traveller', 'traveler', 'passenger'], 'bn' => ['পাসপোর্ট হোল্ডার', 'যাত্রী'], 'bl' => ['passport holder', 'jatri'],
                            'intent' => 'clients', 'ops' => ['count', 'show']],

        /* --- books --- */
        'profit'        => ['en' => ['profit', 'loss', 'margin', 'net profit', 'p&l', 'income statement'], 'bn' => ['লাভ', 'লোকসান', 'মুনাফা', 'মার্জিন'], 'bl' => ['lav', 'profit', 'munafa'],
                            'intent' => 'profit_loss', 'ops' => ['total', 'show', 'compare', 'trend', 'explain']],
        'expense'       => ['en' => ['expense', 'expenses', 'spending', 'cost', 'overhead'], 'bn' => ['খরচ', 'ব্যয়'], 'bl' => ['khoroch', 'expense'],
                            'intent' => 'expenses', 'ops' => ['total', 'count', 'show', 'rank', 'compare']],
        'budget'        => ['en' => ['budget', 'over budget', 'budget variance'], 'bn' => ['বাজেট', 'বাজেট ছাড়িয়ে'], 'bl' => ['budget'],
                            'intent' => 'budget', 'ops' => ['total', 'show', 'compare']],
        'account'       => ['en' => ['account', 'ledger', 'chart of accounts', 'account balance'], 'bn' => ['হিসাব', 'খতিয়ান', 'অ্যাকাউন্ট'], 'bl' => ['hisab', 'account', 'ledger'],
                            'intent' => 'account_ledger', 'ops' => ['total', 'show', 'explain']],
        'journal'       => ['en' => ['journal', 'journal entry', 'posting', 'voucher'], 'bn' => ['জার্নাল', 'ভাউচার', 'এন্ট্রি'], 'bl' => ['journal', 'voucher'],
                            'intent' => 'journal', 'ops' => ['count', 'show', 'explain']],
        'trial_balance' => ['en' => ['trial balance', 'do the books balance'], 'bn' => ['ট্রায়াল ব্যালান্স'], 'bl' => ['trial balance'],
                            'intent' => 'trial_balance', 'ops' => ['show', 'explain']],
        'balance_sheet' => ['en' => ['balance sheet', 'net worth', 'assets and liabilities'], 'bn' => ['ব্যালান্স শিট', 'সম্পদ ও দায়'], 'bl' => ['balance sheet'],
                            'intent' => 'balance_sheet', 'ops' => ['show', 'total']],
        'loan'          => ['en' => ['loan', 'loans', 'emi', 'borrowing'], 'bn' => ['ঋণ', 'লোন', 'কিস্তি'], 'bl' => ['loan', 'rin', 'kisti'],
                            'intent' => 'loans', 'ops' => ['total', 'count', 'show']],
        'advance'       => ['en' => ['advance', 'advances', 'salary advance'], 'bn' => ['অগ্রিম', 'অগ্রিম বেতন'], 'bl' => ['ogrim', 'advance'],
                            'intent' => 'advances', 'ops' => ['total', 'count', 'show']],
        'tax'           => ['en' => ['tax', 'vat', 'tds', 'mushak'], 'bn' => ['কর', 'ভ্যাট', 'টিডিএস', 'মুশক'], 'bl' => ['tax', 'vat', 'tds'],
                            'intent' => 'tax', 'ops' => ['total', 'show', 'explain']],

        /* --- people --- */
        'payroll'       => ['en' => ['payroll', 'salary', 'salaries', 'wage bill'], 'bn' => ['বেতন', 'পে-রোল', 'মজুরি'], 'bl' => ['beton', 'payroll', 'salary'],
                            'intent' => 'payroll', 'ops' => ['total', 'count', 'show', 'compare', 'explain'], 'by' => ['explain' => 'deduction_rules']],
        'payslip'       => ['en' => ['payslip', 'payslips', 'pay slip'], 'bn' => ['পে-স্লিপ', 'বেতন স্লিপ'], 'bl' => ['payslip'],
                            'intent' => 'payroll_unpaid', 'ops' => ['count', 'show', 'locate']],
        'deduction'     => ['en' => ['deduction', 'late deduction', 'absence deduction'], 'bn' => ['কর্তন', 'দেরির কর্তন'], 'bl' => ['kortoN', 'deduction'],
                            'intent' => 'deduction_rules', 'ops' => ['explain', 'total', 'show']],
        'overtime'      => ['en' => ['overtime', 'ot', 'extra hours'], 'bn' => ['ওভারটাইম', 'অতিরিক্ত সময়'], 'bl' => ['overtime'],
                            'intent' => 'overtime', 'ops' => ['explain', 'total', 'show']],
        'employee'      => ['en' => ['employee', 'staff', 'people', 'headcount', 'team'], 'bn' => ['কর্মী', 'কর্মচারী', 'জনবল', 'লোক'], 'bl' => ['kormi', 'staff', 'lok'],
                            'intent' => 'headcount', 'ops' => ['count', 'show', 'rank', 'compare'], 'by' => ['rank' => 'staff_ranking', 'compare' => 'departments']],
        'department'    => ['en' => ['department', 'departments', 'division'], 'bn' => ['বিভাগ', 'ডিপার্টমেন্ট'], 'bl' => ['bibhag', 'department'],
                            'intent' => 'departments', 'ops' => ['count', 'show', 'compare', 'rank']],
        'attendance'    => ['en' => ['attendance', 'present', 'absent', 'who is in'], 'bn' => ['হাজিরা', 'উপস্থিত', 'অনুপস্থিত'], 'bl' => ['hajira', 'upossthit'],
                            'intent' => 'attendance_today', 'ops' => ['count', 'show', 'compare']],
        'lateness'      => ['en' => ['late', 'late comers', 'punctuality'], 'bn' => ['দেরি', 'দেরিতে এসেছে'], 'bl' => ['deri', 'late'],
                            'intent' => 'late_today', 'ops' => ['count', 'show', 'rank'], 'by' => ['rank' => 'chronic_late']],
        'leave'         => ['en' => ['leave', 'leaves', 'holiday request', 'on leave'], 'bn' => ['ছুটি', 'ছুটির আবেদন'], 'bl' => ['chuti', 'leave'],
                            'intent' => 'leaves', 'ops' => ['count', 'show', 'total']],
        'holiday'       => ['en' => ['holiday', 'holidays', 'public holiday'], 'bn' => ['সরকারি ছুটি', 'ছুটির দিন'], 'bl' => ['holiday'],
                            'intent' => 'holidays', 'ops' => ['count', 'show']],
        'request'       => ['en' => ['employee request', 'requests', 'staff request'], 'bn' => ['কর্মীর আবেদন', 'আবেদন'], 'bl' => ['abedon', 'request'],
                            'intent' => 'employee_requests', 'ops' => ['count', 'show', 'total']],
        'person'        => ['en' => ['evaluate', 'performance of', 'how is doing', 'profile of'], 'bn' => ['মূল্যায়ন', 'কেমন করছে'], 'bl' => ['mullayon', 'kemon korche'],
                            'intent' => 'evaluate_person', 'ops' => ['show', 'rank'], 'by' => ['rank' => 'staff_ranking']],

        /* --- work --- */
        'task'          => ['en' => ['task', 'tasks', 'workload', 'assignment'], 'bn' => ['কাজ', 'টাস্ক', 'কাজের চাপ'], 'bl' => ['kaj', 'task'],
                            'intent' => 'tasks', 'ops' => ['count', 'show', 'rank']],
        'project'       => ['en' => ['project', 'projects', 'delivery', 'milestone'], 'bn' => ['প্রকল্প', 'প্রজেক্ট'], 'bl' => ['project', 'prokolpo'],
                            'intent' => 'projects', 'ops' => ['count', 'show', 'rank']],
        'todo'          => ['en' => ['to do', 'todo', 'office todo', 'checklist'], 'bn' => ['করণীয়', 'অফিস কাজ'], 'bl' => ['todo', 'koroniyo'],
                            'intent' => 'todos', 'ops' => ['count', 'show']],

        /* --- market --- */
        'lead'          => ['en' => ['lead', 'leads', 'pipeline', 'prospect', 'funnel'], 'bn' => ['লিড', 'পাইপলাইন', 'সম্ভাব্য গ্রাহক'], 'bl' => ['lead', 'pipeline'],
                            'intent' => 'pipeline', 'ops' => ['count', 'total', 'show', 'rank']],
        'customer'      => ['en' => ['customer', 'customers', 'client', 'clients'], 'bn' => ['গ্রাহক', 'ক্লায়েন্ট'], 'bl' => ['grahok', 'customer'],
                            'intent' => 'customers', 'ops' => ['count', 'show', 'rank']],
        'supplier'      => ['en' => ['supplier', 'suppliers', 'vendor', 'vendors'], 'bn' => ['সরবরাহকারী', 'ভেন্ডর'], 'bl' => ['supplier', 'vendor'],
                            'intent' => 'suppliers', 'ops' => ['count', 'show', 'rank']],

        /* --- the day --- */
        'brief'         => ['en' => ['brief', 'briefing', 'summary', 'where do we stand', 'status'], 'bn' => ['ব্রিফ', 'সারসংক্ষেপ', 'অবস্থা'], 'bl' => ['brief', 'obostha'],
                            'intent' => 'brief', 'ops' => ['show']],
        'approval'      => ['en' => ['approval', 'approvals', 'waiting on me', 'sign off'], 'bn' => ['অনুমোদন', 'আমার অনুমোদন'], 'bl' => ['onumodon', 'approval'],
                            'intent' => 'approvals', 'ops' => ['count', 'show', 'total', 'act']],
        'risk'          => ['en' => ['risk', 'risks', 'problem', 'what needs attention', 'red flag'], 'bn' => ['ঝুঁকি', 'সমস্যা', 'নজর দিতে'], 'bl' => ['jhuki', 'somossa'],
                            'intent' => 'risks', 'ops' => ['count', 'show']],
        'anomaly'       => ['en' => ['anomaly', 'anomalies', 'unusual', 'duplicate', 'suspicious'], 'bn' => ['অস্বাভাবিক', 'ডুপ্লিকেট', 'সন্দেহজনক'], 'bl' => ['onnorokom', 'duplicate'],
                            'intent' => 'anomalies', 'ops' => ['count', 'show']],
        'company'       => ['en' => ['company', 'companies', 'group', 'per company'], 'bn' => ['কোম্পানি', 'প্রতিষ্ঠান'], 'bl' => ['company', 'protishthan'],
                            'intent' => 'company_compare', 'ops' => ['count', 'show', 'compare', 'rank']],
        'screen'        => ['en' => ['screen', 'page', 'menu', 'report', 'module'], 'bn' => ['স্ক্রিন', 'পাতা', 'মেনু', 'রিপোর্ট'], 'bl' => ['screen', 'menu', 'report'],
                            'intent' => 'navigation', 'ops' => ['locate', 'show', 'explain'], 'by' => ['explain' => 'howto']],
    ];

    /* ---------------- qualifiers: the slots that narrow it ---------------- */

    public const QUALIFIERS = [
        'period_today'   => ['en' => ['today', 'right now'], 'bn' => ['আজ', 'আজকে', 'এখন'], 'bl' => ['aj', 'ajke', 'ekhon']],
        'period_month'   => ['en' => ['this month', 'month to date'], 'bn' => ['এ মাসে', 'এই মাসে', 'চলতি মাসে'], 'bl' => ['ei mase', 'e mase']],
        'period_last'    => ['en' => ['last month', 'previous month'], 'bn' => ['গত মাসে', 'আগের মাসে'], 'bl' => ['goto mase']],
        'period_year'    => ['en' => ['this year', 'year to date'], 'bn' => ['এ বছরে', 'এই বছরে'], 'bl' => ['ei bochore']],
        'scope_company'  => ['en' => ['for epal travels', 'for it solutions', 'per company', 'by company'], 'bn' => ['কোম্পানি অনুযায়ী'], 'bl' => ['company onujayi']],
        'scope_dept'     => ['en' => ['by department', 'per department'], 'bn' => ['বিভাগ অনুযায়ী'], 'bl' => ['bibhag onujayi']],
        'order_top'      => ['en' => ['top 5', 'top five', 'biggest', 'largest'], 'bn' => ['সবচেয়ে বড়', 'শীর্ষ পাঁচ'], 'bl' => ['top 5', 'sobcheye boro']],
        'state_overdue'  => ['en' => ['overdue', 'past due', 'unpaid', 'pending'], 'bn' => ['বকেয়া', 'মেয়াদ পার', 'অপরিশোধিত'], 'bl' => ['bokeya', 'overdue']],
    ];

    /* ---------------- aspects: what you can ask ABOUT one named record ----------------

       This is where the space really opens. "Imran's payroll", "what is Imran's
       payroll", "payroll of Imran", "ইমরানের বেতন", "Imran er beton", "take me to
       Imran's payslip", "give pay to Imran" are one aspect (payroll) on one instance
       (a person) said six ways in three scripts. 107 staff × 16 aspects × the phrasings
       below × 3 scripts is the hundred thousand — and none of it is written by hand.
    */
    public const ASPECTS = [
        'profile'    => ['en' => ['profile', 'details', 'information', 'record', 'who is'], 'bn' => ['প্রোফাইল', 'তথ্য', 'পরিচয়'], 'bl' => ['profile', 'totho']],
        'payroll'    => ['en' => ['payroll', 'salary', 'pay', 'wage', 'net pay', 'gross'], 'bn' => ['বেতন', 'পে-রোল', 'মজুরি'], 'bl' => ['beton', 'payroll', 'salary']],
        'payslip'    => ['en' => ['payslip', 'pay slip', 'salary slip'], 'bn' => ['পে-স্লিপ', 'বেতন স্লিপ'], 'bl' => ['payslip', 'beton slip']],
        'attendance' => ['en' => ['attendance', 'presence', 'present days'], 'bn' => ['হাজিরা', 'উপস্থিতি'], 'bl' => ['hajira', 'attendance']],
        'lateness'   => ['en' => ['late', 'lateness', 'punctuality', 'late days'], 'bn' => ['দেরি', 'দেরির'], 'bl' => ['deri', 'late']],
        'leave'      => ['en' => ['leave', 'leaves', 'holidays taken'], 'bn' => ['ছুটি'], 'bl' => ['chuti', 'leave']],
        'loan'       => ['en' => ['loan', 'emi', 'borrowing'], 'bn' => ['ঋণ', 'লোন'], 'bl' => ['loan', 'rin']],
        'advance'    => ['en' => ['advance', 'advance salary'], 'bn' => ['অগ্রিম'], 'bl' => ['ogrim', 'advance']],
        'task'       => ['en' => ['task', 'tasks', 'workload', 'work'], 'bn' => ['কাজ', 'টাস্ক'], 'bl' => ['kaj', 'task']],
        'project'    => ['en' => ['project', 'projects'], 'bn' => ['প্রকল্প', 'প্রজেক্ট'], 'bl' => ['project']],
        'request'    => ['en' => ['request', 'requests', 'application'], 'bn' => ['আবেদন'], 'bl' => ['abedon', 'request']],
        'evaluation' => ['en' => ['evaluation', 'performance', 'score', 'rating', 'how is doing'], 'bn' => ['মূল্যায়ন', 'পারফরম্যান্স', 'স্কোর'], 'bl' => ['mullayon', 'performance']],
        'ledger'     => ['en' => ['ledger', 'account', 'statement', 'balance'], 'bn' => ['খতিয়ান', 'হিসাব', 'ব্যালান্স'], 'bl' => ['hisab', 'ledger', 'balance']],
        'contact'    => ['en' => ['phone', 'number', 'email', 'contact'], 'bn' => ['ফোন', 'নম্বর', 'ইমেইল', 'যোগাযোগ'], 'bl' => ['phone', 'number', 'email']],
        'resignation'=> ['en' => ['resignation', 'notice', 'last working day'], 'bn' => ['পদত্যাগ', 'ইস্তফা'], 'bl' => ['podottag', 'resignation']],
        'department' => ['en' => ['department', 'designation', 'team', 'reports to'], 'bn' => ['বিভাগ', 'পদবি'], 'bl' => ['bibhag', 'podobi']],
    ];

    /** the record kinds a name can resolve to, and where each is read from */
    public const INSTANCE_KINDS = ['employee', 'party', 'passenger', 'project', 'company', 'account', 'invoice'];

    /**
     * Find the named record a sentence is about, and which aspect of it is wanted.
     * @return array{kind:?string,id:mixed,label:?string,aspect:?string,possessive:bool}
     */
    public static function instance(string $raw, string $normalised, string $lang, array $D): array
    {
        $out = ['kind' => null, 'id' => null, 'label' => null, 'aspect' => null, 'possessive' => false];
        $n = ' ' . trim($normalised) . ' ';

        // which aspect is being asked for
        $asp = self::best(self::ASPECTS, $n, $lang);
        $out['aspect'] = $asp['id'] ?? null;

        // a possessive construction is a strong hint that a name is present
        $out['possessive'] = (bool) preg_match('/\b\w+(?:\'s|s\')\s|\bof\s+[A-Z]|\ber\s+\w|[\x{0985}-\x{09B9}]\x{09C7}\x{09B0}\s/u', $raw);

        // Staff and clients are keyed in Latin, but the boss types বাংলা. Fold the
        // sentence to a rough Latin skeleton as well, so "রাশেদুল ইসলামের বেতন"
        // reaches the same record as "Rashedul Islam payroll".
        $nSkel = self::skeleton($n);
        $best = null;
        $consider = function (string $kind, $id, ?string $label) use (&$best, $n, $nSkel) {
            $label = trim((string) $label);
            if ($label === '' || mb_strlen($label) < 3) return;
            $hit = mb_stripos($n, mb_strtolower($label)) !== false;
            if (!$hit) {
                // both sides reduced the same way, or the comparison is meaningless
                $sk = self::skeleton($label);
                $hit = strlen($sk) >= 5 && str_contains($nSkel, $sk);
            }
            if (!$hit) return;
            if ($best === null || mb_strlen($label) > mb_strlen($best['label'])) $best = ['kind' => $kind, 'id' => $id, 'label' => $label];
        };
        foreach ($D['employees'] ?? [] as $r) $consider('employee', $r['id'] ?? null, $r['name'] ?? null);
        foreach ($D['customers'] ?? [] as $r) $consider('party', $r['id'] ?? null, $r['name'] ?? null);
        foreach ($D['suppliers'] ?? [] as $r) $consider('party', $r['id'] ?? null, $r['name'] ?? null);
        foreach ($D['passport_holders'] ?? [] as $r) $consider('passenger', $r['id'] ?? null, $r['name'] ?? null);
        foreach ($D['projects'] ?? [] as $r) $consider('project', $r['id'] ?? null, $r['project_name'] ?? null);
        foreach ($D['companies'] ?? [] as $r) { $consider('company', $r['id'] ?? null, $r['name'] ?? null); $consider('company', $r['id'] ?? null, $r['short_name'] ?? null); }
        foreach ($D['accounts'] ?? [] as $r) $consider('account', $r['code'] ?? null, $r['name'] ?? null);
        // a first name is enough when it is unambiguous
        if ($best === null) {
            $firsts = [];
            foreach ($D['employees'] ?? [] as $r) {
                $f = mb_strtolower(trim(explode(' ', trim((string) ($r['name'] ?? '')))[0] ?? ''));
                if (mb_strlen($f) >= 4) $firsts[$f][] = $r;
            }
            foreach ($firsts as $f => $rs) {
                if (count($rs) !== 1 || mb_stripos($n, ' ' . $f) === false) continue;
                $best = ['kind' => 'employee', 'id' => $rs[0]['id'], 'label' => $rs[0]['name']];
                break;
            }
        }
        // an invoice or account code is a name too
        if ($best === null && preg_match('/\b((?:INV|VINV)[- ]?\d{2,})\b/i', $raw, $m)) $best = ['kind' => 'invoice', 'id' => strtoupper(str_replace(' ', '', $m[1])), 'label' => strtoupper($m[1])];
        if ($best === null && preg_match('/\b(\d{4})\b/', $raw, $m)) {
            foreach ($D['accounts'] ?? [] as $a) if ((string) ($a['code'] ?? '') === $m[1]) { $best = ['kind' => 'account', 'id' => $m[1], 'label' => $m[1] . ' ' . ($a['name'] ?? '')]; break; }
        }
        if ($best !== null) { $out['kind'] = $best['kind']; $out['id'] = $best['id']; $out['label'] = $best['label']; }
        return $out;
    }

    /** Bengali letters → a rough Latin skeleton. Not a transliteration standard:
        just enough that a name typed in বাংলা lands on the Latin record it means. */
    private const ROMAN = [
        'ক্ষ' => 'kh', 'জ্ঞ' => 'gg', 'ঞ্চ' => 'nc', 'ঙ্গ' => 'ng',
        'অ' => 'o', 'আ' => 'a', 'ই' => 'i', 'ঈ' => 'i', 'উ' => 'u', 'ঊ' => 'u', 'ঋ' => 'ri',
        'এ' => 'e', 'ঐ' => 'oi', 'ও' => 'o', 'ঔ' => 'ou',
        'ক' => 'k', 'খ' => 'kh', 'গ' => 'g', 'ঘ' => 'gh', 'ঙ' => 'ng',
        'চ' => 'c', 'ছ' => 'ch', 'জ' => 'j', 'ঝ' => 'jh', 'ঞ' => 'n',
        'ট' => 't', 'ঠ' => 'th', 'ড' => 'd', 'ঢ' => 'dh', 'ণ' => 'n',
        'ত' => 't', 'থ' => 'th', 'দ' => 'd', 'ধ' => 'dh', 'ন' => 'n',
        'প' => 'p', 'ফ' => 'f', 'ব' => 'b', 'ভ' => 'v', 'ম' => 'm',
        'য' => 'j', 'র' => 'r', 'ল' => 'l', 'শ' => 's', 'ষ' => 's', 'স' => 's', 'হ' => 'h',
        'ড়' => 'r', 'ঢ়' => 'r', 'য়' => 'y', 'ৎ' => 't', 'ং' => 'ng', 'ঃ' => '', 'ঁ' => '',
        'া' => 'a', 'ি' => 'i', 'ী' => 'i', 'ু' => 'u', 'ূ' => 'u', 'ৃ' => 'ri',
        'ে' => 'e', 'ৈ' => 'oi', 'ো' => 'o', 'ৌ' => 'ou', '্' => '',
    ];

    public static function romanise(string $s): string
    {
        $s = strtr($s, self::ROMAN);
        return mb_strtolower(preg_replace('/[^a-z0-9 ]+/i', '', $s) ?? $s);
    }

    /** Consonant skeleton. Vowels are guesswork across scripts, and so is the h in a
        romanised digraph — বাংলা শ gives "s" where the passport spells it "sh", so
        Rashedul/রাশেদুল only meet once both are dropped. */
    public static function skeleton(string $s): string
    {
        $s = self::romanise($s);
        return preg_replace('/[aeiouhwy\s]+/', '', $s) ?? $s;
    }

    /** (kind, aspect) → the handler that answers it */
    public static function routeInstance(?string $kind, ?string $aspect): ?string
    {
        if ($kind === null) return null;
        return match ($kind) {
            'employee'  => 'person_aspect',
            'party'     => 'party_balance',
            // a named passenger, project, company or account is that record — not the
            // aggregate screen. Routing them to the list answered without ever naming
            // the thing asked about, which reads as EON not having heard the question.
            'passenger', 'project', 'company', 'account', 'invoice' => 'record_aspect',
            default     => null,
        };
    }

    /* ---------------- matching ---------------- */

    /** the scripts a language should be matched against */
    private static function scripts(string $lang): array
    {
        return $lang === 'en' ? ['en', 'bl'] : ['bn', 'bl', 'en'];
    }

    /** longest surface form that appears in the normalised sentence wins */
    private static function best(array $table, string $n, string $lang): ?array
    {
        $bestId = null; $bestLen = 0; $bestForm = '';
        foreach ($table as $id => $def) {
            foreach (self::scripts($lang) as $s) {
                foreach ((array) ($def[$s] ?? []) as $form) {
                    $f = trim((string) $form);
                    if ($f === '' || mb_strlen($f) <= $bestLen) continue;
                    if (Nlu::mentions($n, $f)) { $bestId = $id; $bestLen = mb_strlen($f); $bestForm = $f; }
                }
            }
        }
        return $bestId === null ? null : ['id' => $bestId, 'form' => $bestForm, 'len' => $bestLen];
    }

    /**
     * Decompose a sentence into verb + subject + qualifiers.
     * @return array{verb:?string,subject:?string,qualifiers:array,intent:?string,confidence:float}
     */
    public static function parse(string $normalised, string $lang = 'en'): array
    {
        $n = ' ' . trim($normalised) . ' ';
        $verb = self::best(self::VERBS, $n, $lang);
        $subject = self::best(self::SUBJECTS, $n, $lang);
        $quals = [];
        foreach (self::QUALIFIERS as $id => $def) {
            foreach (self::scripts($lang) as $s) {
                foreach ((array) ($def[$s] ?? []) as $form) {
                    if (Nlu::mentions($n, (string) $form)) { $quals[] = $id; continue 3; }
                }
            }
        }
        $vid = $verb['id'] ?? null;
        $sid = $subject['id'] ?? null;
        $intent = self::route($vid, $sid);
        // a subject alone is a usable question ("cash?"); a verb alone is not
        $confidence = 0.0;
        if ($sid !== null) $confidence = $vid !== null ? 0.9 : 0.6;
        return ['verb' => $vid, 'subject' => $sid, 'qualifiers' => array_values(array_unique($quals)),
                'intent' => $intent, 'confidence' => $confidence];
    }

    /** (verb, subject) → the handler that can answer it */
    public static function route(?string $verb, ?string $subject): ?string
    {
        if ($subject === null || !isset(self::SUBJECTS[$subject])) return null;
        $def = self::SUBJECTS[$subject];
        if ($verb !== null && isset($def['by'][$verb])) return $def['by'][$verb];
        // an action on anything is an instruction to record
        if ($verb === 'act') return 'remind';
        // asking where something lives is always navigation
        if ($verb === 'locate' && $subject !== 'screen') return 'navigation';
        return $def['intent'] ?? null;
    }

    /** every (subject, verb) pair the matrix should be able to answer */
    public static function pairs(): array
    {
        $out = [];
        foreach (self::SUBJECTS as $sid => $def) {
            foreach ((array) ($def['ops'] ?? ['show']) as $vid) {
                $out[] = ['subject' => $sid, 'verb' => $vid, 'intent' => self::route($vid, $sid)];
            }
        }
        return $out;
    }

    /** a natural sentence for a (verb, subject) pair — used to probe coverage */
    public static function phrase(string $verb, string $subject, string $lang): ?string
    {
        $s = self::SUBJECTS[$subject][$lang][0] ?? (self::SUBJECTS[$subject]['en'][0] ?? null);
        if ($s === null) return null;
        $t = self::TEMPLATES[$lang][$verb] ?? null;
        if ($t === null) return null;
        return str_replace('{s}', $s, $t);
    }

    /** how each verb is said about a subject, per script */
    public const TEMPLATES = [
        'en' => ['total' => 'how much {s} do we have', 'count' => 'how many {s} are there', 'show' => 'show me the {s}',
                 'rank' => 'which {s} is the biggest', 'compare' => 'compare {s} with last month', 'trend' => 'what is the {s} forecast',
                 'explain' => 'how is {s} calculated', 'locate' => 'where is the {s} screen', 'act' => 'remind me about {s}'],
        'bn' => ['total' => '{s} কত', 'count' => '{s} কতগুলো', 'show' => '{s} দেখাও',
                 'rank' => 'সবচেয়ে বেশি {s} কোনটা', 'compare' => 'গত মাসের সঙ্গে {s} তুলনা করো', 'trend' => '{s} এর পূর্বাভাস কী',
                 'explain' => '{s} কীভাবে হিসাব হয়', 'locate' => '{s} স্ক্রিন কোথায়', 'act' => '{s} নিয়ে মনে করিয়ে দিও'],
        'bl' => ['total' => '{s} koto', 'count' => '{s} kotogulo', 'show' => '{s} dekhao',
                 'rank' => 'sobcheye beshi {s} konta', 'compare' => 'goto maser songe {s} tulona koro', 'trend' => '{s} er forecast ki',
                 'explain' => '{s} kivabe hisab hoy', 'locate' => '{s} screen kothay', 'act' => '{s} niye remind koro'],
    ];
}
