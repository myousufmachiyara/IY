<?php

namespace App\Exports;

use App\Models\Bid;
use Maatwebsite\Excel\Concerns\{FromQuery, WithHeadings, WithMapping};

class BidsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private array $filters = []) {}

    public function query()
    {
        return Bid::query()->with(['agent', 'customer'])
            ->when($this->filters['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($this->filters['from'] ?? null, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($this->filters['to'] ?? null, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->orderBy('auction_date');
    }

    public function headings(): array
    {
        return ['Lot', 'Auction House', 'Date', 'Agent', 'Customer', 'Make', 'Model', 'Year', 'Grade', 'Chassis', 'Max Bid (¥)', 'Result'];
    }

    public function map($bid): array
    {
        return [
            $bid->lot_no, $bid->auction_house, optional($bid->auction_date)->format('Y-m-d'),
            $bid->agent?->name, $bid->customer?->name, $bid->make, $bid->model, $bid->year,
            $bid->grade, $bid->chassis_no, $bid->max_bid, $bid->result,
        ];
    }
}