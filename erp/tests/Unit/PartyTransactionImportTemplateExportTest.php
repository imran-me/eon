<?php

namespace Tests\Unit;

use App\Exports\PartyTransactionImportTemplateExport;
use PHPUnit\Framework\TestCase;

class PartyTransactionImportTemplateExportTest extends TestCase
{
    public function test_it_exposes_the_controlled_financial_import_columns(): void
    {
        $columns = PartyTransactionImportTemplateExport::columns();

        foreach (['party_type', 'party_code_or_name', 'transaction_kind', 'invoice_reference', 'amount', 'bank_name', 'external_reference'] as $required) {
            $this->assertContains($required, $columns);
        }

        $this->assertSame($columns, array_values(array_unique($columns)));
    }
}
