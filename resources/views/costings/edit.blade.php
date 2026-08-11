@extends('layouts.app')

@section('title', 'Costing | ' . $vehicle->label())

@section('content')

<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Costing — {{ $vehicle->label() }}</h2>
                <a href="{{ route('vehicles.show', $vehicle) }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Back to Vehicle</a>
            </header>

            @include('vehicles._tabs', ['vehicle' => $vehicle, 'active' => 'costing'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

                <div class="row mb-3">
                    <div class="col-md-4"><strong>Customer:</strong> {{ $vehicle->customer->name }}</div>
                    <div class="col-md-4"><strong>Vendor:</strong> {{ $vehicle->vendor->name ?? '—' }}</div>
                    <div class="col-md-4"><strong>Buying Price:</strong> ¥{{ number_format($vehicle->buying_price) }}</div>
                </div>

                <div class="row">
                    {{-- ===== QUADRANT 1: COST BREAKDOWN — Super Admin / Accountant ONLY, not even read-only for anyone else ===== --}}
                    @can('costings.edit')
                        @if(auth()->user()->canBackdate())
                        <div class="col-lg-6">
                            <div class="card bg-light border mb-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><i class="fa fa-receipt me-1"></i> Cost Breakdown</h5>
                                    <form method="POST" action="{{ route('costings.update', $vehicle) }}">
                                        @csrf @method('PUT')
                                        <div class="row form-group">
                                            <div class="col-6 mb-2">
                                                <label>Vendor Commission %</label>
                                                <input type="number" step="0.01" class="form-control calc-input" id="vendor_commission_percent" name="vendor_commission_percent" value="{{ old('vendor_commission_percent', $costing->vendor_commission_percent) }}" required>
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
                                                <label>Auction Platform Commission Charges (¥)</label>
                                                <input type="number" class="form-control calc-input" id="auction_commission" name="auction_commission" value="{{ old('auction_commission', $costing->auction_commission) }}" min="0" required>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <label>Freight Charges (¥)</label>
                                                <input type="number" class="form-control calc-input" id="freight_charges" name="freight_charges" value="{{ old('freight_charges', $costing->freight_charges) }}" min="0" required>
                                                <small class="text-muted">Auto-synced from Shipment once freight is confirmed there.</small>
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
                                            <tr><td>+ Auction Platform Commission Charges</td><td class="text-end">¥<span id="calc_auction">{{ number_format($costing->auction_commission) }}</span></td></tr>
                                            <tr><td>+ Freight Charges</td><td class="text-end">¥<span id="calc_freight">{{ number_format($costing->freight_charges) }}</span></td></tr>
                                            <tr><td>+ Misc Expenses</td><td class="text-end">¥<span id="calc_misc">{{ number_format($costing->misc_expenses) }}</span></td></tr>
                                            <tr class="fw-bold border-top"><td>Total Costing FOR COMPANY</td><td class="text-end">¥<span id="calc_total_costing">{{ number_format($costing->total_costing) }}</span></td></tr>
                                        </table>
                                        <button type="submit" class="btn btn-primary w-100">Save Costing</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif
                    @endcan

                    {{-- ===== QUADRANT 2: PRICING & SELLING PRICE — everyone with page access ===== --}}
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
                                    <tr class="fw-bold border-top"><td>COST PRICE FOR AGENT</td><td class="text-end">¥<span id="calc_cost_price">{{ number_format($costing->sale_price) }}</span></td></tr>
                                </table>

                                @can('costings.edit')
                                    @if(is_null($vehicle->selling_price))
                                    <form method="POST" action="{{ route('costings.selling', $vehicle) }}">
                                        @csrf @method('PUT')
                                        <div class="mb-1">
                                            <label>Selling Price (¥) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="selling_price" name="selling_price"
                                                   value="{{ old('selling_price', $costing->sale_price) }}"
                                                   min="{{ $costing->sale_price }}" required oninput="recalcAgentEarning()">
                                        </div>
                                        <small class="text-muted d-block mb-2">NOTE: minimum is Cost Price (¥{{ number_format($costing->sale_price) }}).</small>
                                        <button type="submit" class="btn btn-primary w-100 mb-3">Save Selling Price</button>
                                    </form>
                                    @else
                                    <p class="mb-3"><strong>Selling Price:</strong> ¥{{ number_format($vehicle->selling_price) }} <span class="badge bg-secondary">Locked</span></p>
                                    @endif
                                @else
                                    <p class="mb-3"><strong>Selling Price:</strong> ¥{{ number_format($vehicle->selling_price ?? $costing->sale_price) }}</p>
                                @endcan
                            </div>
                        </div>

                        {{-- ===== QUADRANT 4: AGENT PROFIT OVERVIEW — everyone with page access ===== --}}
                        <div class="card bg-light border mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="fa fa-user-tie me-1"></i> Agent Profit Overview</h5>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td>Selling Price</td><td class="text-end">¥<span id="ap_selling_price">{{ number_format($vehicle->selling_price ?? $costing->sale_price) }}</span></td></tr>
                                    <tr><td>Agent Commission ({{ $vehicle->agent->sales_commission_percent ?? 15 }}%)</td><td class="text-end">¥<span id="ap_agent_commission">{{ number_format($costing->agent_commission_amount) }}</span></td></tr>
                                    <tr><td>Fixed Bonus</td><td class="text-end">¥{{ number_format($costing->agent_bonus) }}</td></tr>
                                    <tr class="fw-bold border-top"><td>Total Agent Earning</td><td class="text-end text-success">¥<span id="ap_total_earning">{{ number_format($costing->agentEarning()) }}</span></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== QUADRANT 3: COMPANY PROFIT OVERVIEW — Super Admin / Accountant ONLY ===== --}}
                @if(auth()->user()->canBackdate())
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h5 class="card-title mb-3"><i class="fa fa-chart-line me-1"></i> Company Profit Overview</h5>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td>Selling Price</td><td class="text-end">¥{{ number_format($vehicle->selling_price ?? 0) }}</td></tr>
                                    <tr><td>Total Costing</td><td class="text-end">¥{{ number_format($costing->total_costing) }}</td></tr>
                                    <tr><td>Agent Commission (Total Earning)</td><td class="text-end">¥{{ number_format($costing->agentEarning()) }}</td></tr>
                                    <tr class="fw-bold border-top"><td>Final Profit</td><td class="text-end {{ $costing->finalProfit() >= 0 ? 'text-success' : 'text-danger' }}">¥{{ number_format($costing->finalProfit()) }}</td></tr>
                                </table>
                                @if(is_null($vehicle->selling_price))
                                    <p class="text-muted small mt-2 mb-0"><i class="fa fa-info-circle"></i> Preview only — awaiting the agent's selling price.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </section>
    </div>
</div>

<script>
const costPriceForAgent0 = {{ $costing->sale_price }};
const totalCosting0 = {{ $costing->total_costing }};
const agentCommissionPercent = {{ $vehicle->agent->sales_commission_percent ?? 15 }};
const agentFixedBonus = {{ (int) ($vehicle->agent->sales_fixed_bonus ?? 0) }};
let costPriceForAgent = costPriceForAgent0;
let totalCosting = totalCosting0;

function formatYen(n) { return Math.round(n).toLocaleString('en-US'); }

function serviceChargeFor(price) {
    if (price <= 500000) return 90000;
    if (price <= 1000000) return 110000;
    return Math.round(price * 0.10);
}

function recalcCostBreakdown() {
    const buyingPrice = {{ $vehicle->buying_price }};
    const vendorPct = parseFloat(document.getElementById('vendor_commission_percent')?.value) || 0;
    const inland = parseFloat(document.getElementById('inland_charges')?.value) || 0;
    const auction = parseFloat(document.getElementById('auction_commission')?.value) || 0;
    const freight = parseFloat(document.getElementById('freight_charges')?.value) || 0;
    const misc = parseFloat(document.getElementById('misc_expenses')?.value) || 0;

    const vendorCommAmount = Math.round(buyingPrice * (vendorPct / 100));
    totalCosting = buyingPrice + vendorCommAmount + inland + auction + freight + misc;
    const serviceCharge = serviceChargeFor(buyingPrice);
    costPriceForAgent = buyingPrice + serviceCharge + inland + freight + misc;

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
    document.getElementById('calc_cost_price').textContent = formatYen(costPriceForAgent);

    recalcAgentEarning();
}

function recalcAgentEarning() {
    const sellingInput = document.getElementById('selling_price');
    const sellingPrice = sellingInput ? (parseFloat(sellingInput.value) || 0) : {{ $vehicle->selling_price ?? $costing->sale_price }};

    const commission = Math.round(Math.max(sellingPrice - costPriceForAgent, 0) * (agentCommissionPercent / 100));
    const totalEarning = commission + agentFixedBonus;

    document.getElementById('ap_selling_price').textContent = formatYen(sellingPrice);
    document.getElementById('ap_agent_commission').textContent = formatYen(commission);
    document.getElementById('ap_total_earning').textContent = formatYen(totalEarning);
}

document.querySelectorAll('.calc-input').forEach(el => el.addEventListener('input', recalcCostBreakdown));
recalcAgentEarning();
</script>

@endsection