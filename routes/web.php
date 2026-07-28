<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\{DashboardController, UserController, RoleController};
use App\Http\Controllers\{CustomerController, VendorController, VehicleController, BidSheetController, BidController};
use App\Http\Controllers\{BiddingResultController, CostingController, InvoiceController, PaymentController};
use App\Http\Controllers\{ShipmentController, VehicleReassignController, DocumentController, VendorPaymentController,
    ExpenseController, AccountingController, ReportController};

// ── Guest ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/unauthorized', fn () => view('unauthorized'))->name('unauthorized')->middleware('auth');

// ── Authenticated ────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('team', UserController::class)->except('show')->middleware('permission:team');
    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:user_roles');

    // Module 2 — Customers, Vehicles & Vendors
    Route::resource('customers', CustomerController::class)->middleware('permission:customers');
    Route::post('customers/{customer}/deposit/receive', [CustomerController::class, 'receiveDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.receive');
    Route::post('customers/{customer}/deposit/approve', [CustomerController::class, 'approveDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.approve');
    Route::post('customers/{customer}/deposit/reject',  [CustomerController::class, 'rejectDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.reject');

    Route::resource('vendors', VendorController::class)->except('show')->middleware('permission:vendors');

    Route::resource('vehicles', VehicleController::class)->middleware('permission:vehicles');
    Route::post('vehicles/{vehicle}/request-invoice', [VehicleController::class, 'requestInvoice'])->middleware('permission:vehicles.edit')->name('vehicles.request_invoice');

    // Module 3 — Bidding
    // NOTE: static routes MUST be declared before Route::resource('bid-sheets', ...)
    // below, otherwise they get swallowed by the resource's GET /bid-sheets/{bid_sheet}
    // route and 404 trying to find a BidSheet matching that path segment.
    Route::get('bid-sheets/template', [BidSheetController::class, 'template'])->middleware('permission:bid_sheets.index')->name('bid-sheets.template');
    Route::put('bid-sheets/bulk-assign-customer', [BidSheetController::class, 'bulkAssignCustomer'])->middleware('permission:bid_sheets.edit')->name('bid-sheets.bulk_assign');
    Route::resource('bid-sheets', BidSheetController::class)->except(['edit', 'update'])->middleware('permission:bid_sheets');

    Route::get('bids',        [BidController::class, 'index'])->middleware('permission:bids.index')->name('bids.index');
    Route::get('bids/export', [BidController::class, 'export'])->middleware('permission:bids.print')->name('bids.export');

    // Module 4 — Results & Costing
    Route::get('results', [BiddingResultController::class, 'index'])->middleware('permission:results.index')->name('results.index');
    Route::post('bids/{bid}/won',  [BiddingResultController::class, 'won'])->middleware('permission:results.edit')->name('bids.won');
    Route::post('bids/{bid}/lost', [BiddingResultController::class, 'lost'])->middleware('permission:results.edit')->name('bids.lost');
    Route::post('bids/{bid}/undo-won', [BiddingResultController::class, 'undoWon'])->middleware('permission:results.edit')->name('bids.undo_won');
    Route::put('bids/{bid}/assign-customer', [BidSheetController::class, 'assignCustomer'])->middleware('permission:bid_sheets.edit')->name('bids.assign_customer');

    Route::get('vehicles/{vehicle}/costing', [CostingController::class, 'show'])->middleware('permission:costings.show')->name('costings.show');
    Route::put('vehicles/{vehicle}/costing', [CostingController::class, 'updateCosting'])->middleware('permission:costings.edit')->name('costing.edit');
    Route::put('vehicles/{vehicle}/selling-price', [CostingController::class, 'updateSellingPrice'])->middleware('permission:costings.edit')->name('costings.selling');

    // Module 5 — Invoicing & Payments
    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices.index')->name('invoices.index');
    Route::post('vehicles/{vehicle}/invoice', [InvoiceController::class, 'store'])->middleware('permission:invoices.create')->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.show')->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:invoices.print')->name('invoices.pdf');
    Route::put('invoices/{invoice}/settle', [InvoiceController::class, 'settle'])->middleware('permission:invoices.edit')->name('invoices.settle');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:invoices.edit')->name('invoices.cancel');

    Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.index')->name('payments.index');
    Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->middleware('permission:payments.edit')->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->middleware('permission:payments.edit')->name('payments.update');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete')->name('payments.destroy');
    Route::get('customers/{customer}/ledger', [PaymentController::class, 'customerLedger'])->middleware('permission:payments.index')->name('payments.customer_ledger');

    // Module 6 — Shipment & Documents
    Route::get('customers/{customer}/shipments/create', [ShipmentController::class, 'create'])->middleware('permission:shipments.create')->name('shipments.create');
    Route::resource('shipments', ShipmentController::class)->only(['index', 'store', 'show', 'edit', 'update'])->middleware('permission:shipments');
    Route::put('shipments/{shipment}/schedule', [ShipmentController::class, 'setSchedule'])->middleware('permission:shipments.edit')->name('shipments.schedule');
    Route::post('shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch'])->middleware('permission:shipments.edit')->name('shipments.dispatch');
    Route::post('shipments/{shipment}/arrive',   [ShipmentController::class, 'arrive'])->middleware('permission:shipments.edit')->name('shipments.arrive');

    Route::post('vehicles/{vehicle}/reassign', [VehicleReassignController::class, 'reassign'])->middleware('permission:vehicles.edit')->name('vehicles.reassign');

    Route::get('vehicles/{vehicle}/documents',  [DocumentController::class, 'index'])->middleware('permission:documents.index')->name('documents.index');
    Route::post('vehicles/{vehicle}/documents', [DocumentController::class, 'store'])->middleware('permission:documents.create')->name('documents.store');
    Route::post('vehicles/{vehicle}/documents/release', [DocumentController::class, 'release'])->middleware('permission:documents.edit')->name('documents.release');
    Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->middleware('permission:documents.edit')->name('documents.edit');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->middleware('permission:documents.edit')->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.delete')->name('documents.destroy');

    // Module 6b — Vendor Payments & Expenses
    Route::resource('vendor-payments', VendorPaymentController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->middleware('permission:vendor_payments');
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->middleware('permission:expenses');

    // Module 7 — Accounting
    Route::middleware('permission:accounting.index')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('chart',       [AccountingController::class, 'chartOfAccounts'])->name('chart');
        Route::post('chart',      [AccountingController::class, 'storeAccount'])->name('chart.store');
        Route::put('chart/{account}', [AccountingController::class, 'updateAccount'])->name('chart.update');
        Route::get('journal',     [AccountingController::class, 'journal'])->name('journal');
        Route::get('ledger/{account}', [AccountingController::class, 'ledger'])->name('ledger');
        Route::get('cash-bank',   [AccountingController::class, 'cashBankBook'])->name('cash_bank');
        Route::get('receivables', [AccountingController::class, 'receivables'])->name('receivables');
        Route::get('payables',    [AccountingController::class, 'payables'])->name('payables');
        Route::get('profit-loss', [AccountingController::class, 'profitLoss'])->name('profit_loss');
    });

    // Module 8 — Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('agent-wise',  [ReportController::class, 'agentWise'])->middleware('permission:reports.agent_wise')->name('agent_wise');
        Route::get('vendor-wise', [ReportController::class, 'vendorWise'])->middleware('permission:reports.vendor_wise')->name('vendor_wise');
        Route::get('bid-wise',    [ReportController::class, 'bidWise'])->middleware('permission:reports.bid_wise')->name('bid_wise');
        Route::get('bid-won',     [ReportController::class, 'bidWon'])->middleware('permission:reports.bid_won')->name('bid_won');
    });
});