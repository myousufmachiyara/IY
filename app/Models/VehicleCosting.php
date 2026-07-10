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

    /**
     * Company service charge tier (yen), based on buying price:
     *   0 – 5 lac      => 90,000
     *   5 – 10 lac     => 110,000
     *   above 10 lac   => 10% of buying price
     * (1 lac = 100,000 yen)
     */
    public static function serviceChargeFor(int $buyingPrice): int
    {
        return match (true) {
            $buyingPrice <= 500_000  => 90_000,
            $buyingPrice <= 1_000_000 => 110_000,
            default                   => (int) round($buyingPrice * 0.10),
        };
    }

    /**
     * Recompute every derived figure from the raw inputs already set on the model,
     * plus the agent's commission % / fixed bonus. Call ->save() after.
     */
    public function recalculate(float $agentCommissionPercent = 15, int $agentFixedBonus = 0): static
    {
        $this->vendor_commission_amount = (int) round($this->buying_price * ($this->vendor_commission_percent / 100));

        // Total costing = buying + vendor commission + inland + auction + freight + misc
        $this->total_costing =
            $this->buying_price + $this->vendor_commission_amount + $this->inland_charges +
            $this->auction_commission + $this->freight_charges + $this->misc_expenses;

        // Sale price = buying + company service charge + inland + freight + misc
        $this->company_service_charge = self::serviceChargeFor($this->buying_price);
        $this->sale_price =
            $this->buying_price + $this->company_service_charge +
            $this->inland_charges + $this->freight_charges + $this->misc_expenses;

        $this->profit = $this->sale_price - $this->total_costing;

        // Agent earning = (profit * 15%) + fixed bonus  (bonus only when there IS profit / a won bid)
        $this->agent_commission_amount = (int) round(max($this->profit, 0) * ($agentCommissionPercent / 100));
        $this->agent_bonus = $agentFixedBonus;

        return $this;
    }

    public function agentEarning(): int
    {
        return $this->agent_commission_amount + $this->agent_bonus;
    }
}