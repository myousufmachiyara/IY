<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleCosting extends Model
{
    protected $fillable = [
        'vehicle_id', 'buying_price', 'vendor_commission_percent', 'vendor_commission_amount',
        'inland_charges', 'auction_commission', 'freight_charges', 'misc_expenses', 'total_costing',
        'company_service_charge', 'sale_price', 'profit',
        'agent_commission_amount', 'agent_bonus', 'prepared_by',
    ];

    protected function casts(): array
    {
        return [
            'buying_price'              => 'integer',
            'vendor_commission_percent' => 'decimal:2',
            'vendor_commission_amount'  => 'integer',
            'inland_charges'            => 'integer',
            'auction_commission'        => 'integer',
            'freight_charges'           => 'integer',
            'misc_expenses'             => 'integer',
            'total_costing'             => 'integer',
            'company_service_charge'    => 'integer',
            'sale_price'                => 'integer',
            'profit'                    => 'integer',
            'agent_commission_amount'   => 'integer',
            'agent_bonus'               => 'integer',
        ];
    }

    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by'); }

    public static function serviceChargeFor(int $buyingPrice): int
    {
        return match (true) {
            $buyingPrice <= 500_000  => 90_000,
            $buyingPrice <= 1_000_000 => 110_000,
            default                   => (int) round($buyingPrice * 0.10),
        };
    }

    /**
     * Recompute every derived figure. $sellingPrice is the AGENT'S actual entered
     * selling price (from vehicles.selling_price) — pass null if not yet set, in
     * which case profit/commission preview against the suggested cost price instead.
     *
     * IMPORTANT: `sale_price` on this model means "Cost Price for Agent" — the
     * agent's floor price and the base their commission is measured against. It is
     * NEVER overwritten with the actual selling price; that lives separately on
     * vehicles.selling_price.
     *
     * CALL SIGNATURE CHANGED — every caller must now pass $sellingPrice FIRST:
     *   recalculate($sellingPrice, $agentCommissionPercent, $agentFixedBonus)
     * The old 2-argument callers (percent, bonus) will silently corrupt the costing
     * if not updated — this is exactly the bug fixed in ShipmentController below.
     */
    public function recalculate(?int $sellingPrice = null, float $agentCommissionPercent = 15, int $agentFixedBonus = 0): static
    {
        $this->vendor_commission_amount = (int) round($this->buying_price * ($this->vendor_commission_percent / 100));

        $this->total_costing =
            $this->buying_price + $this->vendor_commission_amount + $this->inland_charges +
            $this->auction_commission + $this->freight_charges + $this->misc_expenses;

        $this->company_service_charge = self::serviceChargeFor($this->buying_price);

        // "Cost Price for Agent" — deliberately excludes vendor/auction commission.
        $this->sale_price =
            $this->buying_price + $this->company_service_charge +
            $this->inland_charges + $this->freight_charges + $this->misc_expenses;

        $sellingPrice ??= $this->vehicle?->selling_price;

        if ($sellingPrice) {
            $this->profit = $sellingPrice - $this->total_costing; // company gross margin at the ACTUAL selling price
            $this->agent_commission_amount = (int) round(max($sellingPrice - $this->sale_price, 0) * ($agentCommissionPercent / 100));
        } else {
            // No selling price yet — preview against the suggested cost price.
            $this->profit = $this->sale_price - $this->total_costing;
            $this->agent_commission_amount = 0;
        }

        $this->agent_bonus = $agentFixedBonus;

        return $this;
    }

    public function agentEarning(): int
    {
        return $this->agent_commission_amount + $this->agent_bonus;
    }

    /** Company's true bottom line, after paying out the agent's full earning. */
    public function finalProfit(): int
    {
        return $this->profit - $this->agentEarning();
    }
}