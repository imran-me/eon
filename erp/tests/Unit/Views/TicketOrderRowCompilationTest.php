<?php

namespace Tests\Unit\Views;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class TicketOrderRowCompilationTest extends TestCase
{
    public function test_ticket_order_row_compiles_to_valid_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        $source = file_get_contents(__DIR__.'/../../../resources/views/dashboard/ticket_direct_sales/partials/order-row.blade.php');

        // view:cache generates PHP without parsing it. TOKEN_PARSE catches unmatched compiled conditionals.
        $tokens = token_get_all($compiler->compileString($source), TOKEN_PARSE);

        $this->assertNotEmpty($tokens);
    }
}
