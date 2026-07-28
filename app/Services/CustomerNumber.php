<?php

namespace App\Services;

use App\Models\Customer;

class CustomerNumber
{
    public static function next(): string
    {
        $seq = Customer::withoutGlobalScopes()->count() + 1;
        return sprintf('IY/CUST/%05d', $seq);
    }
}