<?php

namespace App\Imports;

use App\Models\{Bid, BidSheet};
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\{ToModel, WithHeadingRow};
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BidsImport implements ToModel, WithHeadingRow
{
    public function __construct(private BidSheet $sheet) {}

    public function model(array $row): ?Bid
    {
        if (empty($row['make']) && empty($row['lot_no'])) {
            return null; // skip blank rows
        }

        return new Bid([
            'bid_sheet_id'  => $this->sheet->id,
            'agent_id'      => $this->sheet->agent_id,
            'customer_id'   => $row['customer_id'] ?? null,
            'lot_no'        => $row['lot_no'] ?? null,
            'auction_house' => $row['auction_house'] ?? null,
            'auction_date'  => $this->date($row['auction_date'] ?? null),
            'make'          => $row['make'] ?? null,
            'model'         => $row['model'] ?? null,
            'year'          => $row['year'] ?? null,
            'grade'         => $row['grade'] ?? null,
            'chassis_no'    => $row['chassis_no'] ?? null,
            'max_bid'       => (int) ($row['max_bid'] ?? 0),
            'result'        => 'pending',
        ]);
    }

    private function date($value): ?string
    {
        if (blank($value)) return null;
        try {
            return is_numeric($value)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString()
                : Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}