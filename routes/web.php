<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\{CustomerController, VehicleController, BidSheetController, BidController};
use App\Http\Controllers\{BiddingResultController, CostingController, InvoiceController, PaymentController};
use App\Http\Controllers\{ShipmentController, VehicleReassignController, DocumentController, VendorPaymentController, 
ExpenseController, AccountingController, ReportController};

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('team', UserController::class)->except('show');
    });

    // Module 2 — Customers & Vehicles
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/deposit',  [CustomerController::class, 'payDeposit'])->name('customers.deposit');
    Route::post('customers/{customer}/complete', [CustomerController::class, 'completeProfile'])->name('customers.complete');
    Route::resource('vehicles', VehicleController::class);

    // Module 3 — Bidding
    Route::resource('bid-sheets', BidSheetController::class)->except(['edit', 'update']);

    Route::middleware('role:super_admin')->group(function () {
        Route::get('bids',        [BidController::class, 'index'])->name('bids.index');
        Route::get('bids/export', [BidController::class, 'export'])->name('bids.export');
    });

    // Module 4 — Results & Costing
    Route::get('results', [BiddingResultController::class, 'index'])->name('results.index');
    Route::post('bids/{bid}/won',  [BiddingResultController::class, 'won'])->name('bids.won');
    Route::post('bids/{bid}/lost', [BiddingResultController::class, 'lost'])->name('bids.lost');

    Route::get('vehicles/{vehicle}/costing', [CostingController::class, 'edit'])->name('costings.edit');
    Route::put('vehicles/{vehicle}/costing', [CostingController::class, 'updateCosting'])->name('costings.update');
    Route::put('vehicles/{vehicle}/selling-price', [CostingController::class, 'updateSellingPrice'])->name('costings.selling');

    // Module 5 — Invoicing & Payments
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('vehicles/{vehicle}/invoice', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::put('invoices/{invoice}/settle', [InvoiceController::class, 'settle'])->name('invoices.settle');

    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('customers/{customer}/ledger', [PaymentController::class, 'customerLedger'])->name('payments.customer_ledger');


    // Module 6 — Shipment & Documents
    Route::get('customers/{customer}/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::resource('shipments', ShipmentController::class)->only(['index', 'store', 'show']);
    Route::put('shipments/{shipment}/schedule', [ShipmentController::class, 'setSchedule'])->name('shipments.schedule');
    Route::post('shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch'])->name('shipments.dispatch');
    Route::post('shipments/{shipment}/arrive',   [ShipmentController::class, 'arrive'])->name('shipments.arrive');

    Route::post('vehicles/{vehicle}/reassign', [VehicleReassignController::class, 'reassign'])->name('vehicles.reassign');

    Route::get('vehicles/{vehicle}/documents',  [DocumentController::class, 'index'])->name('documents.index');
    Route::post('vehicles/{vehicle}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('vehicles/{vehicle}/documents/release', [DocumentController::class, 'release'])->name('documents.release');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Module 6b — Vendor Payments & Expenses
    Route::resource('vendor-payments', VendorPaymentController::class)->only(['index', 'store']);
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'destroy']);

    // Module 7 — Accounting
    Route::middleware('role:super_admin|accountant')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('chart',       [AccountingController::class, 'chartOfAccounts'])->name('chart');
        Route::get('journal',     [AccountingController::class, 'journal'])->name('journal');
        Route::get('ledger/{account}', [AccountingController::class, 'ledger'])->name('ledger');
        Route::get('cash-bank',   [AccountingController::class, 'cashBankBook'])->name('cash_bank');
        Route::get('receivables', [AccountingController::class, 'receivables'])->name('receivables');
        Route::get('payables',    [AccountingController::class, 'payables'])->name('payables');
        Route::get('profit-loss', [AccountingController::class, 'profitLoss'])->name('profit_loss');
    });

    // Module 8 — Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('agent-wise',  [ReportController::class, 'agentWise'])->name('agent_wise');
        Route::get('vendor-wise', [ReportController::class, 'vendorWise'])->name('vendor_wise');
        Route::get('bid-wise',    [ReportController::class, 'bidWise'])->name('bid_wise');
        Route::get('bid-won',     [ReportController::class, 'bidWon'])->name('bid_won');
    });
});