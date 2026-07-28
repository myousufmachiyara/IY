<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithHeadings};

class BidTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['LOT123', 'USS Tokyo', '2026-08-01', 'Toyota', 'Prius', '2020', 'S Package', 'ZVW51-1234567', 1200000, 1],
        ];
    }

    public function headings(): array
    {
        // customer_id intentionally removed (#10) — assignment happens through the
        // app's Assign Customer / Bulk Assign UI, not the import file.
        return ['lot_no', 'auction_house', 'auction_date', 'make', 'model', 'year', 'grade', 'chassis_no', 'max_bid', 'priority'];
    }
}