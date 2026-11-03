<?php

namespace Tests\Unit;

use App\Exports\PartyInvoiceImportTemplateExport;
use PHPUnit\Framework\TestCase;

class PartyInvoiceImportTemplateExportTest extends TestCase
{
    public function test_it_exposes_invoice_group_and_service_line_columns(): void
    {
        $this->assertSame([
            'document_group', 'party_type', 'party_code_or_name', 'invoice_date', 'due_date',
            'service_type', 'description', 'passenger_name', 'quantity', 'unit_price',
            'revenue_account_code', 'notes',
        ], PartyInvoiceImportTemplateExport::columns());
    }
}
