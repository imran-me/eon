#!/usr/bin/env node
/* ============================================================
   make-qa-corpus.mjs — build the question bank EON is tested on.

   A director does not ask one question per report; he asks the same
   thing eight ways, in two languages, half of them typed in roman
   Bangla on a phone. This writes every one of those into
   tools/qa-corpus.json, tagged with the intent it must land on, so
   qa-run.php can prove coverage instead of asserting it.

     node tools/make-qa-corpus.mjs
   ============================================================ */

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT = resolve(HERE, 'qa-corpus.json');

/* ------------------------------------------------------------------
   Each entry: intent → { section, en[], bn[], bl[] }
   en = English, bn = Bangla script, bl = romanised Bangla (Banglish)
   ------------------------------------------------------------------ */
const BANK = {
  /* ---------------- executive ---------------- */
  brief: {
    section: 'Executive',
    en: ['give me the brief', 'brief me', "what's the morning brief", 'update me', 'catch me up',
         'how are things', 'where do we stand', 'give me the rundown', "today's report", 'overall status'],
    bn: ['আজকের ব্রিফ দাও', 'ব্রিফ দাও', 'আজকের অবস্থা কি', 'খবর কি', 'সার্বিক অবস্থা বলো',
         'আপডেট দাও', 'সংক্ষেপে বলো আজকের অবস্থা', 'পরিস্থিতি কেমন', 'আজকের সারসংক্ষেপ দাও'],
    bl: ['ajker brief dao', 'brief dao', 'obostha ki', 'khobor ki', 'update dao'],
  },
  focus: {
    section: 'Executive',
    en: ['what should I focus on', 'what should I do today', 'what needs my attention',
         'what is urgent', 'what is the top priority', 'what matters most right now',
         'what should I deal with first', 'my priorities today'],
    bn: ['আজ কী করা উচিত', 'কোনটা আগে দেখব', 'কী করব আজ', 'সবচেয়ে জরুরি কোনটা',
         'কোনদিকে নজর দেওয়া দরকার', 'আজকের অগ্রাধিকার কী', 'প্রথমে কোনটা ধরব'],
    bl: ['ki kora uchit aj', 'ki korbo', 'kon ta age dekhbo', 'joruri ki'],
  },
  approvals: {
    section: 'Executive',
    en: ['what is waiting for my approval', 'show me the approval queue', 'anything pending my sign off',
         'what needs my authorisation', 'how many approvals are waiting', 'what am I holding up',
         'is anything awaiting approval'],
    bn: ['আমার অনুমোদনের অপেক্ষায় কী আছে', 'অনুমোদনের সারিতে কী', 'কী কী অনুমোদন বাকি',
         'আমার সই লাগবে কোথায়', 'কতগুলো অনুমোদন ঝুলে আছে'],
    bl: ['onumodon ki ache', 'amar soi lagbe kothay', 'approval koto ache'],
  },
  risks: {
    section: 'Executive',
    en: ['what am I missing', 'what should worry me', 'what is wrong', 'any red flags',
         'what are the risks', 'what is my blind spot', 'anything bad I should know',
         'what problems do we have'],
    bn: ['আমি কী মিস করছি', 'কী নিয়ে চিন্তা করা উচিত', 'কোথায় সমস্যা', 'কী ঝুঁকি আছে',
         'কোথায় গণ্ডগোল', 'কী ভুল হচ্ছে', 'দুর্বলতা কোথায়'],
    bl: ['ki miss korchi', 'ki somossa ache', 'jhuki ki', 'ki bhul hocche'],
  },
  anomalies: {
    section: 'Executive',
    en: ['anything unusual', 'any anomalies', 'anything suspicious', 'any duplicate entries',
         'does anything look irregular', 'is anything off in the books', 'any strange transactions'],
    bn: ['অস্বাভাবিক কিছু আছে', 'কোনো গরমিল আছে', 'সন্দেহজনক কিছু', 'কোনো অনিয়ম',
         'ডুপ্লিকেট কিছু আছে', 'হিসাবে কোনো অসঙ্গতি'],
    bl: ['gormil ache', 'osbabhabik kichu', 'duplicate ache'],
  },
  health: {
    section: 'Executive',
    en: ['what is the health score', 'how healthy is the business', 'give me the scorecard',
         'overall company health', 'how is the business doing'],
    bn: ['স্বাস্থ্য স্কোর কত', 'ব্যবসার অবস্থা কেমন', 'সার্বিক স্কোর দাও', 'কেমন চলছে ব্যবসা'],
    bl: ['health score koto', 'business kemon cholche'],
  },
  since: {
    section: 'Executive',
    en: ['what changed since yesterday', 'anything new', 'what happened since yesterday',
         'what is new today', 'any changes today'],
    bn: ['গতকাল থেকে কী পরিবর্তন', 'নতুন কী', 'গতকাল থেকে কী হয়েছে', 'নতুন কিছু আছে'],
    bl: ['gotokal theke ki notun', 'notun ki ache'],
  },
  forecast: {
    section: 'Executive',
    en: ['forecast next quarter', 'what is the outlook', 'project the next three months',
         'where are we heading', 'what does the trend say', 'predict next month',
         'give me a projection'],
    bn: ['আগামী প্রান্তিকের পূর্বাভাস দাও', 'সামনে কী হবে', 'ভবিষ্যতে কী দাঁড়াবে',
         'আগামী মাসে কী হবে', 'প্রবণতা কী বলছে', 'পূর্বাভাস দাও'],
    bl: ['forecast dao', 'agami mash e ki hobe', 'samne ki hobe'],
  },

  why: {
    section: 'Reasoning',
    en: ['why is profit down', 'why are we making a loss', 'what is driving the loss',
         'why is cash so tight', 'what is eating the cash', 'why is the salary bill so high',
         'why are people late', 'why is delivery slipping', 'why has spending gone up',
         'why is nobody paying us', 'what caused the overdue payments', 'explain why the margin fell',
         'what is behind the cash position', 'why do we owe so much'],
    bn: ['লাভ কেন কমছে', 'কেন লোকসান হচ্ছে', 'লোকসানের কারণ কি',
         'কেন টাকা আটকে আছে', 'নগদ কেন কম', 'বেতনের খরচ কেন এত বেশি',
         'কর্মীরা কেন দেরি করে', 'কাজ কেন পিছিয়ে যাচ্ছে', 'খরচ কেন বেড়েছে',
         'কেন কেউ টাকা দিচ্ছে না', 'দেনা কেন এত', 'কী কারণে মুনাফা কমল'],
    bl: ['lav keno komche', 'keno lokshan hocche', 'taka keno atke ache',
         'beton keno eto beshi', 'kormira keno deri kore', 'khoroch keno bereche'],
  },

  /* ---------------- cash ---------------- */
  cash: {
    section: 'Cash & Bank',
    en: ['cash position', 'how much cash do we have', 'how much money do we have',
         'what is our liquidity', 'cash in hand', 'how much is available',
         'what is the cash position right now', 'total funds'],
    bn: ['ক্যাশ কত আছে', 'হাতে কত টাকা আছে', 'নগদ কত', 'কত টাকা আছে',
         'তহবিলে কত', 'হাতে আর ব্যাংকে মোট কত', 'নগদ অবস্থা কী'],
    bl: ['cash koto ache', 'hate koto taka', 'taka koto ache', 'nogod koto'],
  },
  bank_accounts: {
    section: 'Cash & Bank',
    en: ['bank balances', 'which bank has the most', 'show me balance by bank',
         'what is in our bank accounts', 'bank wise balance', 'list the bank accounts'],
    bn: ['ব্যাংকে কত টাকা', 'কোন ব্যাংকে কত আছে', 'ব্যাংক অনুযায়ী ব্যালেন্স',
         'ব্যাংক অ্যাকাউন্টে কত', 'কোন ব্যাংকে সবচেয়ে বেশি'],
    bl: ['bank e koto taka', 'kon bank e koto', 'bank balance koto'],
  },
  petty_cash: {
    section: 'Cash & Bank',
    en: ['petty cash balance', 'how much petty cash', 'what is in the float',
         'office cash balance', 'petty cash position'],
    bn: ['পেটি ক্যাশ কত', 'খুচরা নগদ কত আছে', 'অফিস ক্যাশ কত', 'ফ্লোটে কত আছে'],
    bl: ['petty cash koto', 'office cash koto'],
  },
  burn_runway: {
    section: 'Cash & Bank',
    en: ['what is our burn rate', 'how long will the cash last', 'how many months of cash do we have',
         'what is the runway', 'will we run out of money', 'how much do we spend a month'],
    bn: ['টাকা কতদিন চলবে', 'মাসে কত খরচ হয়', 'হাতের টাকায় কত মাস চলবে',
         'ব্যয়ের হার কত', 'টাকা কি শেষ হয়ে যাবে'],
    bl: ['taka koto din cholbe', 'mase koto khoroch', 'koto mash cholbe'],
  },

  /* ---------------- receivable / payable ---------------- */
  receivables: {
    section: 'Receivable & Payable',
    en: ['who owes us money', 'what are our receivables', 'how much is due to us',
         'show me the debtors', 'how much do we have to collect', 'receivables aging',
         'who has not paid us', 'what is outstanding from customers'],
    bn: ['কে আমাদের টাকা দেবে', 'আমাদের পাওনা কত', 'কার কাছে টাকা পাব',
         'বকেয়া আদায় কত', 'গ্রাহকদের কাছে কত পাওনা', 'কত টাকা পাওয়ার আছে'],
    bl: ['ke taka debe', 'amader pawna koto', 'taka pabo koto'],
  },
  payables: {
    section: 'Receivable & Payable',
    en: ['what do we owe', 'who do we owe money to', 'show me the payables',
         'how much do we have to pay', 'what bills are due', 'payables aging',
         'what is outstanding to suppliers'],
    bn: ['কাকে টাকা দিতে হবে', 'আমাদের দেনা কত', 'কত টাকা পরিশোধ করতে হবে',
         'কার কাছে দেনা আছে', 'কী কী বিল বাকি'],
    bl: ['kake taka dite hobe', 'dena koto', 'koto taka dite hobe'],
  },
  overdue_payments: {
    section: 'Receivable & Payable',
    en: ['what payments are overdue', 'anything past due', 'what is due this week',
         'show me late payments', 'what is due today'],
    bn: ['কোন পেমেন্টের তারিখ পেরিয়েছে', 'কী কী বকেয়া', 'এই সপ্তাহে কী দিতে হবে',
         'মেয়াদোত্তীর্ণ পেমেন্ট কত'],
    bl: ['overdue koto', 'ei soptahe ki dite hobe'],
  },

  /* ---------------- reports ---------------- */
  trial_balance: {
    section: 'Accounts',
    en: ['show me the trial balance', 'does the trial balance tie', 'is the trial balance balanced',
         'debits and credits total', 'trial balance status'],
    bn: ['রেওয়ামিল দেখাও', 'ট্রায়াল ব্যালেন্স মিলছে কি', 'ডেবিট ক্রেডিট মিলছে',
         'রেওয়ামিল ঠিক আছে'],
    bl: ['trial balance mile', 'rewamil dekhao'],
  },
  balance_sheet: {
    section: 'Accounts',
    en: ['show me the balance sheet', 'what is our net worth', 'total assets and liabilities',
         'what is the financial position', 'how much equity do we have'],
    bn: ['স্থিতিপত্র দেখাও', 'নিট সম্পদ কত', 'মোট সম্পদ আর দায় কত',
         'ব্যালেন্স শীট দাও', 'আর্থিক অবস্থা কী'],
    bl: ['balance sheet dao', 'net worth koto'],
  },
  profit_loss: {
    section: 'Accounts',
    en: ['how much profit this month', 'are we profitable', 'show me the P&L',
         'what is the net profit', 'profit and loss for last month', 'what is our margin',
         'did we make money this month', 'income statement'],
    bn: ['এ মাসে লাভ কত', 'লাভ হচ্ছে কি', 'লাভ লোকসান দেখাও', 'নিট মুনাফা কত',
         'গত মাসে লাভ কত হয়েছে', 'মার্জিন কত', 'লোকসান হচ্ছে কি'],
    bl: ['lav koto', 'e mase lav koto', 'munafa koto', 'lokshan hocche ki'],
  },
  revenue: {
    section: 'Accounts',
    en: ['how much revenue this month', 'what is our turnover', 'how much did we sell',
         'total income this month', 'what is the top line'],
    bn: ['এ মাসে কত বিক্রি', 'আয় কত হয়েছে', 'রাজস্ব কত', 'টার্নওভার কত',
         'কত বিক্রি হয়েছে'],
    bl: ['bikri koto', 'ay koto hoyeche', 'revenue koto'],
  },
  expenses: {
    section: 'Accounts',
    en: ['how much did we spend this month', 'total expenses', 'what is our expenditure',
         'how much has gone out', 'spending this month'],
    bn: ['এ মাসে কত খরচ হয়েছে', 'মোট খরচ কত', 'ব্যয় কত', 'কত টাকা খরচ হলো'],
    bl: ['khoroch koto hoyeche', 'mot khoroch koto'],
  },
  expense_by_category: {
    section: 'Accounts',
    en: ['what is our biggest expense', 'expenses by category', 'where is the money going',
         'show me the expense breakdown', 'which head is costing most', 'top 5 expense categories',
         'what are we spending on'],
    bn: ['কোন খাতে সবচেয়ে বেশি খরচ', 'খরচ কোন খাতে বেশি', 'খাত অনুযায়ী খরচ দেখাও',
         'টাকা কোথায় যাচ্ছে', 'খরচের বড় খাত কোনটা'],
    bl: ['kon khate khoroch beshi', 'khoroch kothay jacche'],
  },
  budget: {
    section: 'Accounts',
    en: ['are we over budget', 'budget vs actual', 'how much of the budget is used',
         'which category is over budget', 'budget variance'],
    bn: ['বাজেট ছাড়িয়েছে কি', 'বাজেটের কত ব্যবহার হয়েছে', 'কোন খাত বাজেটের বেশি',
         'বাজেটের সাথে ফারাক কত'],
    bl: ['budget er beshi hoyeche ki', 'budget koto baki'],
  },
  account_ledger: {
    section: 'Accounts',
    en: ['show me the ledger for 1011', 'what is the balance on account 2210',
         'account 6110 ledger', 'read account 1311', 'what is in account 2111',
         'ledger for account 1013'],
    bn: ['১০১১ হিসাবের খতিয়ান দেখাও', '২২১০ হিসাবে কত আছে', '৬১১০ হিসাবের ব্যালেন্স',
         'হিসাব ১৩১১ পড়ো'],
    bl: ['1011 er khotian dekhao', 'account 2210 e koto'],
  },
  journal: {
    section: 'Accounts',
    en: ['how many journal entries are there', 'show me the journal entries',
         'what are the recent postings', 'journal entries by source'],
    bn: ['কতগুলো জার্নাল এন্ট্রি আছে', 'জার্নাল এন্ট্রি দেখাও', 'সাম্প্রতিক দাখিলা দেখাও'],
    bl: ['journal entry koto', 'dakhila dekhao'],
  },
  loans: {
    section: 'Accounts',
    en: ['what loans are outstanding', 'how much loan is left', 'show me the staff loans',
         'what is the EMI', 'any loans running'],
    bn: ['কত ঋণ বাকি আছে', 'কর্মীদের ঋণ কত', 'কিস্তি কত', 'কোনো ঋণ চলছে কি'],
    bl: ['rin koto baki', 'loan koto ache', 'kisti koto'],
  },
  advances: {
    section: 'Accounts',
    en: ['how much advance salary is outstanding', 'show me the advances',
         'who has taken an advance', 'advance salary total'],
    bn: ['অগ্রিম বেতন কত বাকি', 'কে অগ্রিম নিয়েছে', 'এডভান্স কত দেওয়া হয়েছে'],
    bl: ['advance koto ache', 'ogrim beton koto'],
  },
  tax: {
    section: 'Accounts',
    en: ['how much VAT do we owe', 'what is the TDS position', 'show me the tax payable',
         'do we have a VAT return', 'income tax payable'],
    bn: ['ভ্যাট কত দিতে হবে', 'উৎসে কর কত', 'ট্যাক্স কত বাকি', 'আয়কর কত'],
    bl: ['vat koto dite hobe', 'tax koto baki'],
  },
  company_compare: {
    section: 'Accounts',
    en: ['which company is doing best', 'compare the companies', 'company wise profit',
         'which company is losing money', 'profit by company', 'which business is doing well',
         'which company is burning money', 'which company has the most revenue',
         'which company has the highest revenue', 'which business is doing best'],
    bn: ['কোন কোম্পানি ভালো করছে', 'কোম্পানি অনুযায়ী লাভ', 'কোন প্রতিষ্ঠান লোকসানে',
         'কোম্পানিগুলোর তুলনা দাও'],
    bl: ['kon company valo korche', 'company onujayi lav'],
  },

  accounts_error: {
    section: 'Bookkeeping',
    en: ['any accounts error', 'is anything wrong with the accounts', 'does anything not add up',
         'any discrepancy in the books', 'are there accounting mistakes', 'anything broken in the accounts'],
    bn: ['হিসাবে কোনো ভুল আছে', 'হিসাবের ভুল আছে কি', 'কোথাও গরমিল আছে',
         'একাউন্টে ভুল আছে', 'হিসাব মিলছে না'],
    bl: ['hisabe bhul ache ki', 'gormil ache ki', 'accounts error ache'],
  },
  fix: {
    section: 'Bookkeeping',
    en: ['how to solve that error', 'how do I fix that', 'how to correct it', 'what is the remedy'],
    bn: ['কিভাবে ঠিক করব', 'কিভাবে সমাধান করব', 'এটা ঠিক করার উপায় কি'],
    bl: ['kivabe thik korbo', 'somadhan ki'],
  },

  /* ---------------- payroll & people ---------------- */
  payroll: {
    section: 'Payroll & People',
    en: ['show me the payroll', 'what is the salary bill', 'payroll for last month',
         'how much is the monthly wage bill', 'payroll overview', 'total salary cost'],
    bn: ['পে-রোল দেখাও', 'বেতন বিল কত', 'গত মাসের বেতন কত হয়েছে',
         'মাসিক বেতন খরচ কত', 'মোট বেতন কত'],
    bl: ['payroll dekhao', 'beton bill koto', 'beton koto'],
  },
  payroll_unpaid: {
    section: 'Payroll & People',
    en: ['has salary been paid', 'whose salary is pending', 'how much salary is unpaid',
         'is payroll settled', 'who has not been paid'],
    bn: ['বেতন পরিশোধ হয়েছে কি', 'কার বেতন বাকি', 'কত বেতন এখনো দেওয়া হয়নি',
         'বেতন কি দেওয়া হয়েছে'],
    bl: ['beton porishodh hoyeche ki', 'kar beton baki'],
  },
  deduction_rules: {
    section: 'Payroll & People',
    en: ['how is late deduction calculated', 'explain the deduction rule',
         'why was money deducted', 'how is salary calculated', 'what is the grace period',
         'how does absent deduction work'],
    bn: ['বিলম্ব কর্তন কিভাবে হয়', 'কর্তনের নিয়ম কী', 'বেতন কিভাবে হিসাব হয়',
         'কেন টাকা কাটা হলো', 'অনুপস্থিতির কর্তন কিভাবে'],
    bl: ['deduction kivabe hoy', 'beton kivabe hisab hoy'],
  },
  overtime: {
    section: 'Payroll & People',
    en: ['how much overtime this month', 'who worked the most overtime', 'overtime hours',
         'is overtime being paid'],
    bn: ['এ মাসে ওভারটাইম কত', 'কে সবচেয়ে বেশি ওভারটাইম করেছে', 'অতিরিক্ত সময় কত'],
    bl: ['overtime koto', 'ke beshi overtime korlo'],
  },
  headcount: {
    section: 'Payroll & People',
    en: ['how many employees do we have', 'what is the headcount', 'total staff strength',
         'how many people work here', 'how many staff are active'],
    bn: ['কতজন কর্মী আছে', 'মোট জনবল কত', 'কত কর্মচারী আছে', 'কতজন কাজ করে'],
    bl: ['kotojon kormi ache', 'koto employee ache'],
  },
  departments: {
    section: 'Payroll & People',
    en: ['how many departments', 'headcount by department', 'which department is biggest',
         'show me the departments'],
    bn: ['কতগুলো বিভাগ আছে', 'বিভাগ অনুযায়ী কতজন', 'সবচেয়ে বড় বিভাগ কোনটা'],
    bl: ['koto bibhag ache', 'bibhag onujayi kotojon'],
  },
  attendance_today: {
    section: 'Payroll & People',
    en: ['who is absent today', 'attendance today', 'who is present', 'how many came today',
         'who is in the office', 'today attendance summary'],
    bn: ['আজ কে অনুপস্থিত', 'আজকের হাজিরা', 'কে কে এসেছে', 'আজ কতজন এসেছে',
         'আজ কে আসেনি', 'উপস্থিতি কেমন'],
    bl: ['aj ke onupostit', 'ajker hajira', 'ke eseche aj'],
  },
  late_today: {
    section: 'Payroll & People',
    en: ['who came late today', 'who was late', 'any latecomers today', 'late arrivals today'],
    bn: ['আজ কে দেরি করে এসেছে', 'কে লেট করেছে', 'আজ কে বিলম্বে এসেছে'],
    bl: ['ke deri kore eseche', 'aj ke late'],
  },
  chronic_late: {
    section: 'Payroll & People',
    en: ['who is always late', 'who is chronically late', 'show me the punctuality problems',
         'late patterns this month', 'who has the worst attendance'],
    bn: ['কে প্রায়ই দেরি করে', 'নিয়মিত কে দেরি করে', 'সময়ানুবর্তিতার সমস্যা কার',
         'কে বারবার লেট করে'],
    bl: ['ke baar baar late kore', 'niyomito ke deri kore'],
  },
  leaves: {
    section: 'Payroll & People',
    en: ['who is on leave', 'any pending leave applications', 'how many leave requests',
         'show me the leave balance', 'is anyone on leave today'],
    bn: ['কে ছুটিতে আছে', 'ছুটির আবেদন কতগুলো', 'ছুটি বাকি কত', 'আজ কে ছুটিতে'],
    bl: ['ke chutite ache', 'chutir abedon koto'],
  },
  holidays: {
    section: 'Payroll & People',
    en: ['when is the next holiday', 'what holidays are coming', 'show me the holiday calendar',
         'is the office closed soon'],
    bn: ['পরের ছুটি কবে', 'সামনে কী কী ছুটি', 'ছুটির ক্যালেন্ডার দেখাও',
         'সরকারি ছুটি কবে'],
    bl: ['porer chuti kobe', 'samne ki chuti ache'],
  },
  employee_requests: {
    section: 'Payroll & People',
    en: ['any employee requests pending', 'show me the staff requests', 'who has applied for something',
         'pending employee applications'],
    bn: ['কর্মীদের কোনো আবেদন আছে', 'কে কী আবেদন করেছে', 'আবেদন কতগুলো ঝুলে আছে'],
    bl: ['kormider abedon ache ki', 'ke ki abedon korche'],
  },
  online_now: {
    section: 'Payroll & People',
    en: ['who is online now', 'who is working right now', 'who is currently active',
         'anyone online'],
    bn: ['এখন কে অনলাইনে', 'এখন কে কাজ করছে', 'কে সক্রিয় আছে'],
    bl: ['ekhon ke online', 'ke kaj korche ekhon'],
  },

  /* ---------------- ops ---------------- */
  tasks: {
    section: 'Operations',
    en: ['how many tasks are overdue', 'show me the tasks', 'who is overloaded',
         'what work is pending', 'task status'],
    bn: ['কতগুলো কাজ বকেয়া', 'টাস্কের অবস্থা কী', 'কার উপর কাজের চাপ বেশি',
         'কী কী কাজ বাকি'],
    bl: ['koto kaj baki', 'task er obostha ki'],
  },
  projects: {
    section: 'Operations',
    en: ['how many projects are running', 'which projects are at risk', 'project status',
         'is any project behind schedule', 'show me the projects'],
    bn: ['কতগুলো প্রকল্প চলছে', 'কোন প্রকল্প ঝুঁকিতে', 'প্রকল্পের অগ্রগতি কেমন',
         'কোন প্রজেক্ট পিছিয়ে আছে'],
    bl: ['koto project cholche', 'kon project jhukite'],
  },
  todos: {
    section: 'Operations',
    en: ['show me the office to-dos', 'what is on the checklist', 'office todo list'],
    bn: ['অফিসের কাজের তালিকা দেখাও', 'চেকলিস্টে কী আছে', 'করণীয় তালিকা'],
    bl: ['office todo dekhao', 'checklist e ki ache'],
  },

  /* ---------------- crm ---------------- */
  pipeline: {
    section: 'CRM',
    en: ['what is in the pipeline', 'how many open leads', 'what is our conversion rate',
         'show me the CRM status', 'any follow ups due', 'how many leads have gone cold'],
    bn: ['পাইপলাইনে কী আছে', 'কতগুলো লিড খোলা', 'কনভার্শন রেট কত',
         'সিআরএম-এর অবস্থা কী', 'কোনো ফলোআপ বাকি আছে'],
    bl: ['pipeline e ki ache', 'koto lead ache', 'conversion rate koto'],
  },
  customers: {
    section: 'CRM',
    en: ['how many customers do we have', 'who is our biggest customer', 'show me the customer list',
         'top customers'],
    bn: ['কতজন গ্রাহক আছে', 'সবচেয়ে বড় গ্রাহক কে', 'গ্রাহকের তালিকা দেখাও'],
    bl: ['koto customer ache', 'boro customer ke'],
  },
  suppliers: {
    section: 'CRM',
    en: ['how many suppliers', 'show me the vendor list', 'who are our suppliers'],
    bn: ['কতজন সরবরাহকারী আছে', 'ভেন্ডরের তালিকা দেখাও', 'সাপ্লায়ার কারা'],
    bl: ['koto supplier ache', 'vendor list dekhao'],
  },

  /* ---------------- meta ---------------- */
  navigation: {
    section: 'ERP navigation',
    en: ['where do I find the payslips', 'where is the trial balance', 'which menu has attendance',
         'where do I set the budget', 'how do I get to the general ledger',
         'where are the payment schedules', 'where do I see the leads',
         'which screen shows the projects', 'where is petty cash', 'where do I find expenses',
         'where are the bank accounts', 'where do I see holidays'],
    bn: ['কোথায় পাবো পে-স্লিপ', 'রেওয়ামিল কোথায় দেখব', 'হাজিরা কোন মেনুতে',
         'বাজেট কোথায় বসাবো', 'খতিয়ান কোথায় পাব', 'পেমেন্ট সূচি কোথায়',
         'লিড কোথায় দেখব', 'প্রকল্প কোন স্ক্রিনে', 'খরচ কোথায় পাবো'],
    bl: ['payslip kothay pabo', 'trial balance kothay', 'hajira kon menu te'],
  },
  howto: {
    section: 'ERP rules',
    en: ['how does an expense get posted', 'what happens when a payment schedule is approved',
         'how does the lead workflow work', 'explain the employee request process',
         'how is a correction handled', 'why must a shared account have no company',
         'what reports is the ERP missing', 'how does overtime work',
         'how is the leave balance worked out'],
    bn: ['খরচের এন্ট্রি কিভাবে বসে', 'পেমেন্ট সূচি অনুমোদন হলে কী হয়',
         'লিডের ধাপগুলো কী', 'কর্মীর আবেদনের প্রক্রিয়া বুঝিয়ে বলো',
         'ভুল এন্ট্রি কিভাবে সংশোধন হয়', 'ছুটির হিসাব কিভাবে হয়',
         'ওভারটাইম কিভাবে কাজ করে'],
    bl: ['khoroch er entry kivabe bose', 'lead er dhap ki', 'chutir hisab kivabe hoy'],
  },
  capabilities: {
    section: 'Meta',
    en: ['what can you do', 'what can I ask you', 'what do you know', 'help me'],
    bn: ['তুমি কী কী পারো', 'কী জিজ্ঞেস করতে পারি', 'তুমি কী জানো'],
    bl: ['tumi ki paro', 'ki jiggesh korte pari'],
  },
  greeting: {
    section: 'Meta',
    en: ['hello', 'good morning', 'hi EON', 'thank you', 'thanks EON'],
    bn: ['হ্যালো', 'শুভ সকাল', 'আসসালামু আলাইকুম', 'ধন্যবাদ'],
    bl: ['salam', 'dhonnobad', 'kemon acho'],
  },
};

/* ------------------------------------------------------------------
   Modifiers — a director rarely asks the bare question. He qualifies
   it by period or by company, and the intent must survive that.
   ------------------------------------------------------------------ */
const PERIOD = {
  en: ['', ' this month', ' last month', ' this year'],
  bn: ['', ' এ মাসে', ' গত মাসে', ' এ বছর'],
  bl: ['', ' ei mash e', ' goto mash e'],
};
const COMPANY = {
  en: ['', ' for Epal Travels', ' for IT Solutions'],
  bn: ['', ' ট্রাভেলসের জন্য', ' আইটির জন্য'],
  bl: ['', ' travels er jonno'],
};
// only these intents make sense with a period or a company qualifier
const TAKES_PERIOD = new Set(['profit_loss', 'revenue', 'expenses', 'expense_by_category',
  'budget', 'payroll', 'payroll_unpaid', 'overtime']);
const TAKES_COMPANY = new Set(['profit_loss', 'revenue', 'expenses', 'cash', 'headcount', 'payroll']);

const rows = [];
let id = 0;

for (const [intent, def] of Object.entries(BANK)) {
  for (const lang of ['en', 'bn', 'bl']) {
    const list = def[lang] || [];
    for (const q of list) {
      rows.push({ id: ++id, q, intent, section: def.section, lang, expect: lang === 'en' ? 'en' : 'bn', kind: 'base' });

      if (TAKES_PERIOD.has(intent)) {
        for (const suf of PERIOD[lang].slice(1)) {
          rows.push({ id: ++id, q: q + suf, intent, section: def.section, lang, expect: lang === 'en' ? 'en' : 'bn', kind: 'period' });
        }
      }
      if (TAKES_COMPANY.has(intent)) {
        for (const suf of COMPANY[lang].slice(1)) {
          rows.push({ id: ++id, q: q + suf, intent, section: def.section, lang, expect: lang === 'en' ? 'en' : 'bn', kind: 'company' });
        }
      }
    }
  }
}

// a handful of named-person questions, filled in by qa-run from the live staff list
const PERSON_TEMPLATES = [
  { q: 'payslip of {name}', intent: 'payroll', lang: 'en' },
  { q: 'what is the salary of {name}', intent: 'payroll', lang: 'en' },
  { q: 'how is {name} performing', intent: 'evaluate_person', lang: 'en' },
  { q: 'tell me about {name}', intent: 'evaluate_person', lang: 'en' },
  { q: '{name} এর বেতন কত', intent: 'payroll', lang: 'bn' },
  { q: '{name} কেমন কাজ করছে', intent: 'evaluate_person', lang: 'bn' },
];

const corpus = {
  generated_by: 'tools/make-qa-corpus.mjs',
  intents: Object.keys(BANK).length,
  sections: [...new Set(Object.values(BANK).map((d) => d.section))],
  counts: {
    total: rows.length,
    en: rows.filter((r) => r.lang === 'en').length,
    bn: rows.filter((r) => r.lang === 'bn').length,
    bl: rows.filter((r) => r.lang === 'bl').length,
  },
  person_templates: PERSON_TEMPLATES,
  questions: rows,
};

mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify(corpus, null, 1), 'utf8');
console.log(`wrote ${OUT}`);
console.log(`  ${rows.length} questions across ${corpus.intents} intents and ${corpus.sections.length} sections`);
console.log(`  english ${corpus.counts.en} · bangla ${corpus.counts.bn} · banglish ${corpus.counts.bl}`);
console.log(`  plus ${PERSON_TEMPLATES.length} person templates expanded at run time`);
