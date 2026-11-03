<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PartyTransactionImportTemplateExport implements WithMultipleSheets
{
    public static function columns(): array
    {
        return ['party_type', 'party_code_or_name', 'transaction_kind', 'invoice_reference', 'amount',
            'bank_name', 'transaction_date', 'transaction_time', 'payment_method', 'external_reference',
            'payer_bank', 'payer_account_or_cheque', 'notes'];
    }

    public function sheets(): array
    {
        return [new PartyTransactionRowsSheet, new PartyTransactionInstructionsSheet];
    }
}

class PartyTransactionRowsSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function headings(): array
    {
        return PartyTransactionImportTemplateExport::columns();
    }

    public function array(): array
    {
        return [
            ['agent', 'EP-AG-001', 'invoice_payment', 'INV-EXAMPLE-001', 25000, 'Office Bank', now()->toDateString(), '10:30', 'bank', 'BANK-STMT-0001', 'Customer Bank', 'TXN12345', 'Exact invoice reference required.'],
            ['vendor', 'Demo Vendor', 'advance', '', 10000, 'Office Bank', now()->toDateString(), '11:00', 'bank', 'BANK-STMT-0002', 'Vendor Bank', 'TXN12346', 'Advance is not allocated to a bill.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M1');

        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1D4ED8']]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 24, 'C' => 20, 'D' => 24, 'E' => 15, 'F' => 22,
            'G' => 18, 'H' => 18, 'I' => 18, 'J' => 24, 'K' => 22, 'L' => 25, 'M' => 45];
    }

    public function title(): string
    {
        return 'Transactions';
    }
}

class PartyTransactionInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['PARTY TRANSACTION IMPORT — READ ME'],
            ['Choose the company on the import page. Every row in one file posts to that company only. Delete both demo rows before importing.'],
            ['party_type', 'agent, vendor, or customer.'],
            ['party_code_or_name', 'Use the ledger code (EP-AG-001 / EP-VN-001 / EP-CU-001) or an exact party name. The party must belong to the selected company.'],
            ['transaction_kind', 'invoice_payment or advance.'],
            ['invoice_reference', 'Required for invoice_payment. Must exactly match one unpaid invoice/bill belonging to that party. Blank for advance.'],
            ['amount', 'Positive amount. Invoice payments cannot exceed the selected document due.'],
            ['bank_name', 'Exact bank/cash account name under the selected company. It must be linked to a chart-of-accounts account.'],
            ['payment_method', 'cash, bank, or cheque.'],
            ['external_reference', 'Required and unique within the company, normally the bank statement reference. It prevents duplicate imports.'],
            ['Accounting', 'Agent/customer invoice: Dr Bank / Cr AR. Vendor invoice: Dr AP / Cr Bank. Customer advance: Dr Bank / Cr Customer Advance. Vendor advance: Dr Supplier Advance / Cr Bank.'],
            ['Workflow', 'Upload creates a draft only. Review/edit it in the application. Accounting is posted atomically only after approval; any error rolls back the complete batch.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3:A12')->getFont()->setBold(true);
        $sheet->getStyle('A1:B12')->getAlignment()->setWrapText(true)->setVertical('top');

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 26, 'B' => 110];
    }

    public function title(): string
    {
        return 'Instructions';
    }
}
