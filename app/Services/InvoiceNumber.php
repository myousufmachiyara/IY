<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceNumber
{
    public static function next(): string
    {
        $year = now()->format('Y');
        $seq  = Invoice::whereYear('created_at', $year)->count() + 1;

        return sprintf('INV-%s-%04d', $year, $seq);
    }
}