# EPAL ERP — Accounts, HR ও Service Payment Posting Guide

প্রস্তুতের তারিখ: ১ অক্টোবর ২০২৬  
উৎস: Local database Chart of Accounts এবং বর্তমান `config/accounts.php` mapping

## ১. মৌলিক নিয়ম

1. Invoice তৈরি হলে revenue তখনই recognise হবে; টাকা পাওয়ার সময় revenue দ্বিতীয়বার credit হবে না।
2. Vendor bill/cost record হলে cost তখনই recognise হবে; vendor-কে টাকা দেওয়ার সময় cost দ্বিতীয়বার debit হবে না।
3. Invoice payment-এর contra হলো Customer Receivable (1311)।
4. Recorded vendor bill payment-এর contra হলো Supplier Payable (2111)।
5. Invoice/bill ছাড়া আগাম টাকা অবশ্যই Advance account-এ যাবে।
6. একই payment Service, Party এবং Bank—তিন জায়গায় আলাদাভাবে record করা যাবে না। একটি entry অন্য ledgerগুলো update করবে।

## ২. গুরুত্বপূর্ণ Control, Cash ও Advance Accounts

| Code | Account | ব্যবহার |
|---|---|---|
| 1013 | Office Cash | অফিসের মূল cash balance |
| 1011 | Petty Cash - Group | Group petty cash pool |
| 1015 | Petty Cash Float | নির্দিষ্ট petty cash float |
| 1051 | bKash Merchant Wallet | bKash receipt/payment |
| 1052 | Nagad Merchant Wallet | Nagad receipt/payment |
| 1053 | Rocket Merchant Wallet | Rocket receipt/payment |
| 1100 series | Bank Accounts | সংশ্লিষ্ট company bank account |
| 1311 | Customer Receivable - General | Customer/agent-এর invoiced পাওনা |
| 1456 | Advance to Supplier | Bill ছাড়া vendor/supplier-কে advance |
| 1520 | VAT Rebate / Current Account | Recoverable/input VAT |
| 2111 | Supplier Payable | Recorded vendor bill-এর দেনা |
| 2150 | IATA / BSP Payable | IATA/BSP payable control |
| 2151 | IATA BSP Weekly Settlement Payable | BSP weekly settlement |
| 2152 | IATA Airline Ticket Payable | Airline ticket payable |
| 2210 | Salaries Payable | Generated but unpaid salary |
| 2240 | Employee Expense Reimbursement Payable | Employee reimbursement due |
| 2260 | VAT Payable | Output VAT liability |
| 2280 | Withholding Tax (TDS/VDS) Payable | Deducted tax payable |
| 2310 | Advance from Customers | Invoice ছাড়া customer advance |
| 2320 | Unearned Travel Revenue | Service এখনো deliver না হওয়া travel receipt |

## ৩. Service Revenue ও Direct Cost Accounts

| Service | Revenue | Direct cost |
|---|---|---|
| Ticket | 4110 — Air Ticket Sales | 5110 — Air Ticket Purchase Cost |
| Visa | 4120 — Visa Service Income | 5120 — Visa Processing Cost |
| Contract Flight | 4150 — Contract Flight & File Revenue | 5150 — Contract Flight & File Cost |
| Contract File | 4150 — Contract Flight & File Revenue | 5150 — Contract Flight & File Cost |
| Travel commission | 4160 — Travel Commission Income | 5160 — Agent & Sub-agent Commission |

বর্তমান configuration-এ Contract Flight ও Contract File একই revenue code 4150 এবং cost code 5150 ব্যবহার করে। আলাদা profitability প্রয়োজন হলে ভবিষ্যতে পৃথক child account খোলা যেতে পারে।

## ৪. Invoice ও Payment Posting

### Customer invoice তৈরি

| ঘটনা | Debit | Credit |
|---|---|---|
| Ticket invoice | 1311 Customer Receivable | 4110 Ticket Sales |
| Visa invoice | 1311 Customer Receivable | 4120 Visa Service Income |
| Contract Flight invoice | 1311 Customer Receivable | 4150 Contract Flight & File Revenue |
| Contract File invoice | 1311 Customer Receivable | 4150 Contract Flight & File Revenue |

### Invoice-এর টাকা গ্রহণ

**Debit:** নির্বাচিত Bank/Cash/Mobile Wallet  
**Credit:** 1311 Customer Receivable

এখানে revenue account ব্যবহার হবে না, কারণ invoice তৈরির সময় revenue ইতিমধ্যে credit হয়েছে।

### Vendor bill/cost তৈরি

**Debit:** সংশ্লিষ্ট service cost (5110/5120/5150)  
**Credit:** 2111 Supplier Payable অথবা applicable IATA payable

### Recorded vendor bill পরিশোধ

**Debit:** 2111 Supplier Payable  
**Credit:** নির্বাচিত Bank/Cash/Wallet

## ৫. Advance ও Unallocated Payment

| ঘটনা | Debit | Credit |
|---|---|---|
| Invoice ছাড়া customer advance | Bank/Cash | 2310 Advance from Customers |
| Service deliver হয়নি এমন travel receipt | Bank/Cash | 2320 Unearned Travel Revenue |
| Bill ছাড়া supplier advance | 1456 Advance to Supplier | Bank/Cash |
| পরে customer invoice-এ advance adjust | 2310 Customer Advance | 1311 Customer Receivable |
| পরে supplier bill-এ advance adjust | 2111 Supplier Payable | 1456 Advance to Supplier |

## ৬. Refund, Cancellation ও Reissue

### Customer sale refund

প্রথমে credit note:

**Debit:** 4910 Sales Returns  
**Credit:** 1311 Customer Receivable

Customer-কে cash/bank refund দিলে:

**Debit:** 1311 Customer Receivable  
**Credit:** Bank/Cash

Invoice-এর অতিরিক্ত receipt liability হিসেবে ধরে থাকলে approved refund payable account ব্যবহার করতে হবে। বর্তমান chart-এ dedicated Customer Refund Payable নেই; Accounts team চাইলে ২৩xx group-এ child account খোলার সিদ্ধান্ত নিতে পারে।

### Vendor/Airline refund

- Vendor bill unpaid থাকলে: **Bank Dr / Supplier Payable Cr**
- আগে supplier advance দেওয়া থাকলে: **Bank Dr / Advance to Supplier Cr**
- IATA/BSP refund receivable হলে: **Bank Dr / 1381 IATA BSP Refund Receivable Cr**
- Purchase return হিসেবে recognise করলে: applicable payable/advance workflow-এর সঙ্গে 5910 Purchase Returns ব্যবহার করতে হবে; একই refund দুইবার income করা যাবে না।

### Contract Flight cancellation/reissue recovery

| ঘটনা | Account code |
|---|---|
| Customer থেকে cancellation penalty recover | 4181 — Cancellation Penalty Recovered |
| Customer থেকে fare difference recover | 4182 — Fare Difference Recovered |
| Customer থেকে rebooking/change fee recover | 4183 — Rebooking & Change Charges Recovered |
| Company cancellation penalty বহন করেছে | 5171 — Airline Cancellation Penalty (Company Borne) |
| Company fare difference বহন করেছে | 5172 — Fare Difference Absorbed |
| Company rebooking/change fee বহন করেছে | 5173 — Rebooking & Change Fees (Company Borne) |

যদি recovery/charge আগে customer invoice-এ যোগ করা হয়, collection হবে Bank Dr / Customer Receivable Cr। Invoice ছাড়া সরাসরি collection হলে তবেই 4181/4182/4183 সরাসরি credit হবে।

## ৭. অন্যান্য Bank Deposit/Withdraw Contra

| ঘটনা | Bank action | Contra account |
|---|---|---|
| Office Cash bank-এ জমা | Deposit | 1013 Office Cash |
| Bank থেকে Office Cash তোলা | Withdraw | 1013 Office Cash |
| Bank-to-bank transfer | Transfer | অপর bank-এর linked account |
| Bank charge | Withdraw/charge | 8510 Bank Charges |
| Card/gateway charge | Withdraw/charge | 8550 Card & Payment Gateway Charges |
| Bank interest পাওয়া | Deposit | 8111 Bank Interest |
| Commission পাওয়া | Deposit | 8120 Commission Income অথবা 4160 Travel Commission |
| Foreign exchange gain | Deposit/adjustment | 8160 Foreign Exchange Gain |
| Foreign exchange loss | Withdraw/adjustment | 8540 Foreign Exchange Loss |
| Unknown receipt/payment | Temporary | 9810 Suspense Account; দ্রুত identify ও reclassify করতে হবে |

## ৮. HR ও Payroll Posting

| ঘটনা | Debit | Credit |
|---|---|---|
| Salary generated | 6110 Salary Expense | 2210 Salaries Payable |
| Salary paid | 2210 Salaries Payable | Bank/Cash |
| Employee salary advance দেওয়া | 1458 Salary Advance | Bank/Cash |
| Salary থেকে advance recovery | 2210 Salaries Payable | 1458 Salary Advance |
| Employee reimbursement recognised | সংশ্লিষ্ট Expense | 2240 Employee Reimbursement Payable |
| Reimbursement paid | 2240 Employee Reimbursement Payable | Bank/Cash |
| Bonus generated | 6120 Bonus & Festival Allowance | 2220 Bonus Payable |

## ৯. কোন screen ব্যবহার করতে হবে

| পরিস্থিতি | Primary screen |
|---|---|
| নির্দিষ্ট Visa invoice payment | Visa Sales → Receive Payment |
| নির্দিষ্ট Ticket invoice payment | Ticket Sales → Receive Payment |
| নির্দিষ্ট Contract Flight invoice payment | Contract Flight → Receive Payment |
| নির্দিষ্ট Contract File invoice payment | Contract File Sales → Receive Payment |
| Party screen থেকে নির্দিষ্ট invoice payment | Party Statement → Allocate to Invoice |
| Bank statement দেখে invoice payment entry | Bank Cash In → Party → Allocate to Invoice |
| Invoice ছাড়া customer advance | Party Statement/Bank → Unallocated; contra 2310 |
| Vendor bill payment | সংশ্লিষ্ট purchase/payable workflow |
| Cash movement, bank fee, capital, loan | Bank section |

## ১০. Operational Control

1. Invoice allocate করলে system contra automatically 1311 করবে এবং manual change বন্ধ/উপেক্ষা করবে।
2. Vendor bill allocate করলে system contra automatically 2111 বা সংশ্লিষ্ট IATA payable করবে।
3. Reference, party, service, invoice number, payment method, company account, date/time সংরক্ষণ করতে হবে।
4. Partial payment invoice due-এর বেশি হতে পারবে না।
5. Refund-এর জন্য original payment edit/delete নয়; credit note/refund transaction ব্যবহার করতে হবে।
6. Bank charge ও VAT মূল payment থেকে আলাদা journal lines হবে।
7. Unallocated receipt নিয়মিত review করে invoice/advance account-এ reclassify করতে হবে।
8. একই payment একাধিক module থেকে record করা নিষিদ্ধ।

## ১১. Accounts Team-এর সিদ্ধান্ত প্রয়োজন

1. Contract Flight ও Contract File-এর 4150/5150 account পৃথক করা হবে কি না।
2. Dedicated Customer Refund Payable account খোলা হবে কি না।
3. General customer advance-এর জন্য 2310 নাকি travel-specific 2320 default হবে।
4. Ticket vendor settlement-এ 2111 নাকি IATA-specific 2150/2151/2152 ব্যবহার হবে—vendor type অনুযায়ী policy নির্ধারণ।
5. VAT recoverable হলে 1520 এবং payable হলে 2260 ব্যবহারের tax policy নিশ্চিত করা।

এই document operational guideline; statutory VAT/tax treatment চূড়ান্ত করার আগে প্রতিষ্ঠানের accountant/tax adviser-এর approval প্রয়োজন।
