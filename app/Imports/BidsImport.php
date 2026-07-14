<?php

namespace App\Imports;

use App\Models\{Bid, BidSheet, Customer};
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\{ToModel, WithHeadingRow};
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BidsImport implements ToModel, WithHeadingRow
{
    /** @var array<int, string> Human-readable reasons for any rows that were skipped. */
    public array $skipped = [];

    public function __construct(private BidSheet $sheet) {}

    public function model(array $row): ?Bid
    {
        if (empty($row['make']) && empty($row['lot_no'])) {
            return null; // skip blank rows silently
        }

        $customerId = $row['customer_id'] ?? null;

        // A row can be uploaded without a customer yet (assigned later) — only
        // validate profile completion when a customer_id is actually provided.
        if ($customerId) {
            $customer = Customer::find($customerId);

            if (! $customer) {
                $this->skipped[] = "Lot {$row['lot_no']}: customer_id {$customerId} not found.";
                return null;
            }

            if (! $customer->isProfileComplete()) {
                $this->skipped[] = "Lot {$row['lot_no']}: customer '{$customer->name}' has an incomplete profile — bidding is not allowed yet.";
                return null;
            }
        }

        return new Bid([
            'bid_sheet_id'  => $this->sheet->id,
            'agent_id'      => $this->sheet->agent_id,
            'customer_id'   => $customerId,
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