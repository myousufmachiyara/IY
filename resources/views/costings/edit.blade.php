@extends('layouts.app')

@section('title', 'Costing | ' . $vehicle->label())

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Costing — {{ $vehicle->label() }}</h2>
                <a href="{{ route('vehicles.show', $vehicle) }}" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Vehicle
                </a>
            </header>

            @include('vehicles._tabs', ['vehicle' => $vehicle, 'active' => 'costing'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <div class="row mb-3">
                    <div class="col-md-4"><strong>Customer:</strong> {{ $vehicle->customer->name }}</div>
                    <div class="col-md-4"><strong>Vendor:</strong> {{ $vehicle->vendor->name ?? '—' }}</div>
                    <div class="col-md-4"><strong>Buying Price:</strong> ¥{{ number_format($vehicle->buying_price) }}</div>
                </div>

                <div class="row">
                    {{-- ================= COST BREAKDOWN ================= --}}
                    <div class="col-lg-6">
                        <div class="card bg-light border mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="fa fa-receipt me-1"></i> Cost Breakdown</h5>

                                @if(auth()->user()->canBackdate())
                                <form method="POST" action="{{ route('costings.update', $vehicle) }}">
                                    @csrf @method('PUT')
                                    <div class="row form-group">
                                        <div class="col-6 mb-2">
                                            <label>Vendor Commission %</label>
                                            <input type="number" step="0.01" class="form-control calc-input" id="vendor_commission_percent"
                                                   name="vendor_commission_percent" value="{{ old('vendor_commission_percent', $costing->vendor_commission_percent) }}" required>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label>Vendor Commission Amt</label>
                                            <input type="text" class="form-control" id="vendor_commission_amount_display" value="¥{{ number_format($costing->vendor_commission_amount) }}" disabled>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label>Inland Charges (¥)</label>
                                            <input type="number" class="form-control calc-input" id="inland_charges" name="inland_charges" value="{{ old('inland_charges', $costing->inland_charges) }}" min="0" required>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label>Auction Commission (¥)</label>
                                            <input type="number" class="form-control calc-input" id="auction_commission" name="auction_commission" value="{{ old('auction_commission', $costing->auction_commission) }}" min="0" required>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label>Freight Charges (¥)</label>
                                            <input type="number" class="form-control calc-input" id="freight_charges" name="freight_charges" value="{{ old('freight_charges', $costing->freight_charges) }}" min="0" required>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label>Misc Expenses (¥)</label>
                                            <input type="number" class="form-control calc-input" id="misc_expenses" name="misc_expenses" value="{{ old('misc_expenses', $costing->misc_expenses) }}" min="0" required>
                                        </div>
                                    </div>

                                    <hr>
                                    <table class="table table-sm table-borderless mb-2">
                                        <tr><td>Buying Price</td><td class="text-end">¥{{ number_format($vehicle->buying_price) }}</td></tr>
                                        <tr><td>+ Vendor Commission</td><td class="text-end">¥<span id="calc_vendor_comm">{{ number_format($costing->vendor_commission_amount) }}</span></td></tr>
                                        <tr><td>+ Inland Charges</td><td class="text-end">¥<span id="calc_inland">{{ number_format($costing->inland_charges) }}</span></td></tr>
                                        <tr><td>+ Auction Commission</td><td class="text-end">¥<span id="calc_auction">{{ number_format($costing->auction_commission) }}</span></td></tr>
                                        <tr><td>+ Freight Charges</td><td class="text-end">¥<span id="calc_freight">{{ number_format($costing->freight_charges) }}</span></td></tr>
                                        <tr><td>+ Misc Expenses</td><td class="text-end">¥<span id="calc_misc">{{ number_format($costing->misc_expenses) }}</span></td></tr>
                                        <tr class="fw-bold border-top"><td>Total Costing</td><td class="text-end">¥<span id="calc_total_costing">{{ number_format($costing->total_costing) }}</span></td></tr>
                                    </table>

                                    <button type="submit" class="btn btn-primary w-100">Save Costing</button>
                                </form>
                                @else
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td>Vendor Commission</td><td class="text-end">{{ $costing->vendor_commission_percent }}% (¥{{ number_format($costing->vendor_commission_amount) }})</td></tr>
                                    <tr><td>Inland Charges</td><td class="text-end">¥{{ number_format($costing->inland_charges) }}</td></tr>
                                    <tr><td>Auction Commission</td><td class="text-end">¥{{ number_format($costing->auction_commission) }}</td></tr>
                                    <tr><td>Freight Charges</td><td class="text-end">¥{{ number_format($costing->freight_charges) }}</td></tr>
                                    <tr><td>Misc Expenses</td><td class="text-end">¥{{ number_format($costing->misc_expenses) }}</td></tr>
                                    <tr class="fw-bold border-top"><td>Total Costing</td><td class="text-end">¥{{ number_format($costing->total_costing) }}</td></tr>
                                </table>
                                <p class="text-muted small mb-0 mt-2"><i class="fa fa-lock"></i> Only Accountant / Super Admin can edit the cost breakdown.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ================= PRICING & SELLING PRICE ================= --}}
                    <div class="col-lg-6">
                        <div class="card bg-light border mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="fa fa-tag me-1"></i> Pricing &amp; Selling Price</h5>

                                <table class="table table-sm table-borderless mb-2">
                                    <tr><td>Buying Price</td><td class="text-end">¥{{ number_format($vehicle->buying_price) }}</td></tr>
                                    <tr><td>+ Company Service Charge</td><td class="text-end">¥<span id="calc_service_charge">{{ number_format($costing->company_service_charge) }}</span></td></tr>
                                    <tr><td>+ Inland Charges</td><td class="text-end">¥<span id="calc_inland2">{{ number_format($costing->inland_charges) }}</span></td></tr>
                                    <tr><td>+ Freight Charges</td><td class="text-end">¥<span id="calc_freight2">{{ number_format($costing->freight_charges) }}</span></td></tr>
                                    <tr><td>+ Misc Expenses</td><td class="text-end">¥<span id="calc_misc2">{{ number_format($costing->misc_expenses) }}</span></td></tr>
                                    <tr class="fw-bold border-top"><td>Suggested Sale Price</td><td class="text-end">¥<span id="calc_suggested_price">{{ number_format($costing->sale_price) }}</span></td></tr>
                                </table>

                                <form method="POST" action="{{ route('costings.selling', $vehicle) }}">
                                    @csrf @method('PUT')
                                    <div class="mb-2">
                                        <label>Actual Selling Price (¥) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="selling_price" name="selling_price"
                                               value="{{ old('selling_price', $vehicle->selling_price ?? $costing->sale_price) }}" min="1" required
                                               oninput="recalcProfit()">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 mb-3">Save Selling Price</button>
                                </form>

                                <hr>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td>Live Profit Preview</td>
                                        <td class="text-end fw-bold" id="calc_profit_preview">¥{{ number_format($costing->profit) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Agent Commission ({{ $vehicle->agent->sales_commission_percent ?? 15 }}%)</td>
                                        <td class="text-end" id="calc_agent_commission_preview">¥{{ number_format($costing->agent_commission_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Fixed Bonus</td>
                                        <td class="text-end">¥{{ number_format($costing->agent_bonus) }}</td>
                                    </tr>
                                    <tr class="fw-bold border-top">
                                        <td>Total Agent Earning</td>
                                        <td class="text-end text-success" id="calc_agent_earning_preview">¥{{ number_format($costing->agentEarning()) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
const buyingPrice = {{ $vehicle->buying_price }};
const agentCommissionPercent = {{ $vehicle->agent->sales_commission_percent ?? 15 }};
const agentFixedBonus = {{ (int) ($vehicle->agent->sales_fixed_bonus ?? 0) }};
let currentTotalCosting = {{ $costing->total_costing }};

// Mirrors VehicleCosting::serviceChargeFor() exactly.
function serviceChargeFor(price) {
    if (price <= 500000) return 90000;
    if (price <= 1000000) return 110000;
    return Math.round(price * 0.10);
}

function formatYen(n) {
    return Math.round(n).toLocaleString('en-US');
}

function recalcCosting() {
    const vendorPct = parseFloat(document.getElementById('vendor_commission_percent')?.value) || 0;
    const inland = parseFloat(document.getElementById('inland_charges')?.value) || 0;
    const auction = parseFloat(document.getElementById('auction_commission')?.value) || 0;
    const freight = parseFloat(document.getElementById('freight_charges')?.value) || 0;
    const misc = parseFloat(document.getElementById('misc_expenses')?.value) || 0;

    const vendorCommAmount = Math.round(buyingPrice * (vendorPct / 100));
    const totalCosting = buyingPrice + vendorCommAmount + inland + auction + freight + misc;
    const serviceCharge = serviceChargeFor(buyingPrice);
    const suggestedPrice = buyingPrice + serviceCharge + inland + freight + misc;

    document.getElementById('vendor_commission_amount_display').value = '¥' + formatYen(vendorCommAmount);
    document.getElementById('calc_vendor_comm').textContent = formatYen(vendorCommAmount);
    document.getElementById('calc_inland').textContent = formatYen(inland);
    document.getElementById('calc_auction').textContent = formatYen(auction);
    document.getElementById('calc_freight').textContent = formatYen(freight);
    document.getElementById('calc_misc').textContent = formatYen(misc);
    document.getElementById('calc_total_costing').textContent = formatYen(totalCosting);

    document.getElementById('calc_service_charge').textContent = formatYen(serviceCharge);
    document.getElementById('calc_inland2').textContent = formatYen(inland);
    document.getElementById('calc_freight2').textContent = formatYen(freight);
    document.getElementById('calc_misc2').textContent = formatYen(misc);
    document.getElementById('calc_suggested_price').textContent = formatYen(suggestedPrice);

    currentTotalCosting = totalCosting;
    recalcProfit();
}

function recalcProfit() {
    const sellingPrice = parseFloat(document.getElementById('selling_price')?.value) || 0;
    const profit = sellingPrice - currentTotalCosting;
    const agentCommission = Math.round(Math.max(profit, 0) * (agentCommissionPercent / 100));
    const agentEarning = agentCommission + agentFixedBonus;

    const profitEl = document.getElementById('calc_profit_preview');
    profitEl.textContent = '¥' + formatYen(profit);
    profitEl.className = 'text-end fw-bold ' + (profit >= 0 ? 'text-success' : 'text-danger');

    document.getElementById('calc_agent_commission_preview').textContent = '¥' + formatYen(agentCommission);
    document.getElementById('calc_agent_earning_preview').textContent = '¥' + formatYen(agentEarning);
}

document.querySelectorAll('.calc-input').forEach(el => el.addEventListener('input', recalcCosting));
</script>

@endsection