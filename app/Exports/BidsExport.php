<?php

namespace App\Exports;

use App\Models\Bid;
use Maatwebsite\Excel\Concerns\{FromQuery, WithHeadings, WithMapping};

class BidsExport implements FromQuery, WithHeadings, WithMapping
{
    private const LABELS = [
        'lot_no' => 'Lot', 'auction_house' => 'Auction House', 'auction_date' => 'Date',
        'agent' => 'Agent', 'customer' => 'Customer', 'make' => 'Make', 'model' => 'Model',
        'year' => 'Year', 'fuel_type' => 'Fuel', 'color' => 'Color', 'engine' => 'Engine',
        'chassis_no' => 'Chassis', 'max_bid' => 'Max Bid (¥)', 'priority' => 'Priority', 'result' => 'Result',
    ];

    public function __construct(private array $filters = [], private array $columns = [])
    {
        if (empty($this->columns)) {
            $this->columns = array_keys(self::LABELS);
        }
    }

    public function query()
    {
        return Bid::query()->with(['agent', 'customer'])
            ->whereNotNull('customer_id')
            ->when($this->filters['agent_ids'] ?? null, fn ($q, $v) => $q->whereIn('agent_id', $v))
            ->when($this->filters['result'] ?? null, fn ($q, $v) => $q->where('result', $v))
            ->when($this->filters['from'] ?? null, fn ($q, $v) => $q->whereDate('auction_date', '>=', $v))
            ->when($this->filters['to'] ?? null, fn ($q, $v) => $q->whereDate('auction_date', '<=', $v))
            ->orderBy('auction_date');
    }

    public function headings(): array
    {
        return array_map(fn ($c) => self::LABELS[$c] ?? $c, $this->columns);
    }

    public function map($bid): array
    {
        $all = [
            'lot_no' => $bid->lot_no, 'auction_house' => $bid->auction_house,
            'auction_date' => optional($bid->auction_date)->format('Y-m-d'),
            'agent' => $bid->agent?->name, 'customer' => $bid->customer?->name,
            'make' => $bid->make, 'model' => $bid->model, 'year' => $bid->year,
            'fuel_type' => $bid->fuel_type, 'color' => $bid->color, 'engine' => $bid->engine,
            'chassis_no' => $bid->chassis_no, 'max_bid' => $bid->max_bid,
            'priority' => $bid->priority, 'result' => $bid->result,
        ];
        return array_map(fn ($c) => $all[$c] ?? '', $this->columns);
    }
}