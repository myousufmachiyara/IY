<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\{DashboardController, UserController, RoleController, ProfileController, FileController, ApprovalController};
use App\Http\Controllers\{CustomerController, VendorController, VehicleController, BidSheetController, BidController};
use App\Http\Controllers\{BiddingResultController, CostingController, InvoiceController, PaymentController};
use App\Http\Controllers\{ShipmentController, VehicleReassignController, DocumentController, VendorPaymentController,
    ExpenseController, AccountingController, ReportController, LogViewerController};

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/unauthorized', fn () => view('unauthorized'))->name('unauthorized')->middleware('auth');

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('files/{path}', [FileController::class, 'show'])->where('path', '.*')->name('files.show');

    Route::resource('team', UserController::class)->except('show')->middleware('permission:members');
    Route::post('team/{team}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:members.edit')->name('team.reset_password');
    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:roles');

    Route::resource('customers', CustomerController::class)->middleware('permission:customers');
    Route::post('customers/{customer}/deposit/receive', [CustomerController::class, 'receiveDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.receive');
    Route::get('customers/{customer}/deposit/edit', [CustomerController::class, 'editDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.edit');
    Route::put('customers/{customer}/deposit', [CustomerController::class, 'updateDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.update');
    Route::post('customers/{customer}/deposit/approve', [CustomerController::class, 'approveDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.approve');
    Route::post('customers/{customer}/deposit/reject',  [CustomerController::class, 'rejectDeposit'])->middleware('permission:customers.edit')->name('customers.deposit.reject');

    Route::resource('vendors', VendorController::class)->except('show')->middleware('permission:vendors');
    Route::resource('vehicles', VehicleController::class)->middleware('permission:vehicle_requirement');
    Route::post('vehicles/{vehicle}/request-invoice', [VehicleController::class, 'requestInvoice'])->middleware('permission:invoices.request')->name('vehicles.request_invoice');
    Route::post('vehicles/{vehicle}/reassign', [VehicleReassignController::class, 'reassign'])->middleware('permission:vehicle_requirement.edit')->name('vehicles.reassign');
    Route::post('vehicles/{vehicle}/reassign-agent', [VehicleReassignController::class, 'reassignAgent'])->middleware('permission:vehicle_requirement.edit')->name('vehicles.reassign_agent');

    Route::get('bid-sheets/template', [BidSheetController::class, 'template'])->middleware('permission:bid_sheets.index')->name('bid-sheets.template');
    Route::put('bid-sheets/bulk-assign-customer', [BidSheetController::class, 'bulkAssignCustomer'])->middleware('permission:bid_sheets.edit')->name('bid-sheets.bulk_assign');
    Route::resource('bid-sheets', BidSheetController::class)->except(['edit', 'update'])->middleware('permission:bid_sheets');
    Route::delete('bids/{bid}', [BidSheetController::class, 'destroyBid'])->middleware('permission:bid_sheets.edit')->name('bids.destroy');

    Route::get('bids',        [BidController::class, 'index'])->middleware('permission:merge_bids.index')->name('bids.index');
    Route::get('bids/export', [BidController::class, 'export'])->middleware('permission:merge_bids.print')->name('bids.export');

    Route::get('results', [BiddingResultController::class, 'index'])->middleware('permission:bid_results.index')->name('results.index');
    Route::get('results/won', [BiddingResultController::class, 'wonList'])->middleware('permission:bid_results.index')->name('results.won');
    Route::get('results/lost', [BiddingResultController::class, 'lostList'])->middleware('permission:bid_results.index')->name('results.lost');
    Route::post('results/bulk-lost', [BiddingResultController::class, 'bulkLost'])->middleware('permission:bid_results.edit')->name('results.bulk_lost');
    Route::post('bids/{bid}/won',  [BiddingResultController::class, 'won'])->middleware('permission:bid_results.edit')->name('bids.won');
    Route::post('bids/{bid}/lost', [BiddingResultController::class, 'lost'])->middleware('permission:bid_results.edit')->name('bids.lost');
    Route::post('bids/{bid}/undo-won', [BiddingResultController::class, 'undoWon'])->middleware('permission:bid_results.edit')->name('bids.undo_won');
    Route::post('bids/{bid}/undo-lost', [BiddingResultController::class, 'undoLost'])->middleware('permission:bid_results.edit')->name('bids.undo_lost');
    Route::put('bids/{bid}/assign-customer', [BidSheetController::class, 'assignCustomer'])->middleware('permission:bid_sheets.edit')->name('bids.assign_customer');
    Route::get('bid-sheets/{bid_sheet}/edit', [BidSheetController::class, 'edit'])->middleware('permission:bid_sheets.edit')->name('bid-sheets.edit');
    Route::put('bid-sheets/{bid_sheet}', [BidSheetController::class, 'update'])->middleware('permission:bid_sheets.edit')->name('bid-sheets.update');
    Route::get('bids/{bid}/edit', [BidSheetController::class, 'editBid'])->middleware('permission:bid_sheets.edit')->name('bids.edit');
    Route::put('bids/{bid}', [BidSheetController::class, 'updateBid'])->middleware('permission:bid_sheets.edit')->name('bids.update');

    Route::get('vehicles/{vehicle}/costing', [CostingController::class, 'show'])->middleware('permission:costings.show')->name('costings.show');
    Route::put('vehicles/{vehicle}/costing', [CostingController::class, 'updateCosting'])->middleware('permission:costings.edit')->name('costings.update');
    Route::put('vehicles/{vehicle}/selling-price', [CostingController::class, 'updateSellingPrice'])->middleware('permission:costings.edit')->name('costings.selling');

    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices.index')->name('invoices.index');
    Route::post('vehicles/{vehicle}/invoice', [InvoiceController::class, 'store'])->middleware('permission:invoices.create')->name('invoices.store');
    Route::get('customers/{customer}/invoices/bulk-create', [InvoiceController::class, 'bulkCreateForm'])->middleware('permission:invoices.create')->name('invoices.bulk_create_form');
    Route::post('customers/{customer}/invoices/bulk', [InvoiceController::class, 'bulkStore'])->middleware('permission:invoices.create')->name('invoices.bulk_store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.show')->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:invoices.print')->name('invoices.pdf');
    Route::put('invoices/{invoice}/settle', [InvoiceController::class, 'settle'])->middleware('permission:invoices.edit')->name('invoices.settle');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:invoices.edit')->name('invoices.cancel');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:invoices.delete')->name('invoices.destroy');
    Route::get('customers/{customer}/invoices/merge', [InvoiceController::class, 'mergeSelectForm'])->middleware('permission:invoices.print')->name('invoices.merge_select');
    Route::post('customers/{customer}/invoices/merge', [InvoiceController::class, 'mergePdf'])->middleware('permission:invoices.print')->name('invoices.merge_pdf');
    Route::post('invoices/merge', [InvoiceController::class, 'mergeSelectedPdf'])->middleware('permission:invoices.print')->name('invoices.merge_selected');
    Route::get('vehicles/{vehicle}/deposit-invoice/pdf', [VehicleController::class, 'depositInvoicePdf'])->middleware('permission:vehicle_requirement.print')->name('vehicles.deposit_invoice_pdf');
    Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.index')->name('payments.index');
    Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::post('payments/{payment}/approve', [PaymentController::class, 'approve'])->middleware('permission:payments.edit')->name('payments.approve');
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->middleware('permission:payments.edit')->name('payments.reject');
    Route::post('payments/{payment}/undo-approval', [PaymentController::class, 'undoApproval'])->middleware('permission:payments.edit')->name('payments.undo_approval');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->middleware('permission:payments.edit')->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->middleware('permission:payments.edit')->name('payments.update');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete')->name('payments.destroy');
    Route::get('customers/{customer}/ledger', [PaymentController::class, 'customerLedger'])->middleware('permission:payments.index')->name('payments.customer_ledger');

    Route::get('approvals', [ApprovalController::class, 'index'])->middleware('permission:pending_approvals.index')->name('approvals.index');

    Route::get('customers/{customer}/shipments/create', [ShipmentController::class, 'create'])->middleware('permission:shipments.create')->name('shipments.create');
    Route::resource('shipments', ShipmentController::class)->only(['index', 'store', 'show', 'edit', 'update'])->middleware('permission:shipments');
    Route::put('shipments/{shipment}/schedule', [ShipmentController::class, 'setSchedule'])->middleware('permission:shipments.edit')->name('shipments.schedule');
    Route::post('shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch'])->middleware('permission:shipments.edit')->name('shipments.dispatch');
    Route::post('shipments/{shipment}/arrive',   [ShipmentController::class, 'arrive'])->middleware('permission:shipments.edit')->name('shipments.arrive');
    Route::post("shipments/{shipment}/undo-dispatch", [ShipmentController::class, "undoDispatch"])->middleware("permission:shipments.edit")->name("shipments.undo_dispatch");
    Route::post("shipments/{shipment}/undo-arrive", [ShipmentController::class, "undoArrive"])->middleware("permission:shipments.edit")->name("shipments.undo_arrive");
    Route::post("shipments/{shipment}/cancel", [ShipmentController::class, "cancel"])->middleware("permission:shipments.delete")->name("shipments.cancel");

    Route::get('vehicles/{vehicle}/documents',  [DocumentController::class, 'index'])->middleware('permission:documents.index')->name('documents.index');
    Route::post('vehicles/{vehicle}/documents', [DocumentController::class, 'store'])->middleware('permission:documents.create')->name('documents.store');
    Route::post('vehicles/{vehicle}/documents/release', [DocumentController::class, 'release'])->middleware('permission:documents.edit')->name('documents.release');
    Route::post('vehicles/{vehicle}/documents/undo-release', [DocumentController::class, 'undoRelease'])->middleware('permission:documents.edit')->name('documents.undo_release');
    Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->middleware('permission:documents.edit')->name('documents.edit');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->middleware('permission:documents.edit')->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.delete')->name('documents.destroy');

    Route::resource('vendor-payments', VendorPaymentController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->middleware('permission:vendor_payments');
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->middleware('permission:expenses');

    Route::middleware('permission:accounting.index')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('chart',       [AccountingController::class, 'chartOfAccounts'])->name('chart');
        Route::post('chart',      [AccountingController::class, 'storeAccount'])->name('chart.store');
        Route::put('chart/{account}', [AccountingController::class, 'updateAccount'])->name('chart.update');
        Route::get('journal',     [AccountingController::class, 'journal'])->name('journal');
        Route::get('ledger/{account}', [AccountingController::class, 'ledger'])->name('ledger');
        Route::get('cash-bank',   [AccountingController::class, 'cashBankBook'])->name('cash_bank');
        Route::get('trial-balance', [AccountingController::class, 'trialBalance'])->name('trial_balance');
        Route::get('balance-sheet', [AccountingController::class, 'balanceSheet'])->name('balance_sheet');
        Route::get('receivables', [AccountingController::class, 'receivables'])->name('receivables');
        Route::get('payables',    [AccountingController::class, 'payables'])->name('payables');
        Route::get('profit-loss', [AccountingController::class, 'profitLoss'])->name('profit_loss');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('agent-wise',    [ReportController::class, 'agentWise'])->middleware('permission:reports.agent_wise')->name('agent_wise');
        Route::get('vendor-wise',   [ReportController::class, 'vendorWise'])->middleware('permission:reports.vendor_wise')->name('vendor_wise');
        Route::get('bid-wise',      [ReportController::class, 'bidWise'])->middleware('permission:reports.bid_wise')->name('bid_wise');
        Route::get('bid-won',       [ReportController::class, 'bidWon'])->middleware('permission:reports.bid_won')->name('bid_won');
        Route::get('customer-wise', [ReportController::class, 'customerWise'])->middleware('permission:reports.customer_wise')->name('customer_wise');
    });

    Route::get('system/logs', [LogViewerController::class, 'index'])->middleware('permission:system.logs')->name('system.logs');
    Route::get('system/logs/{file}/download', [LogViewerController::class, 'download'])->middleware('permission:system.logs')->name('system.logs.download');
});