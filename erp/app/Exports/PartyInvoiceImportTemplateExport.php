<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PartyInvoiceImportTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public static function columns(): array
    {
        return ['document_group', 'party_type', 'party_code_or_name', 'invoice_date', 'due_date', 'service_type', 'description', 'passenger_name', 'quantity', 'unit_price', 'revenue_account_code', 'notes'];
    }

    public function headings(): array
    {
        return self::columns();
    }

    public function array(): array
    {
        $date = now()->toDateString();

        return [
            ['DEMO-INV-001', 'agent', 'EP-AG-001', $date, $date, 'ticketing', 'DAC-DXB return ticket', 'Demo Passenger', 1, 25000, config('accounts.ticket_sales_revenue'), 'Replace demo party before upload'],
            ['DEMO-INV-001', 'agent', 'EP-AG-001', $date, $date, 'visa', 'Visa processing', 'Demo Passenger', 1, 15000, config('accounts.visa_sales_revenue'), 'Same document_group creates one invoice'],
        ];
    }

    public function title(): string
    {
        return 'Invoices';
    }
}
