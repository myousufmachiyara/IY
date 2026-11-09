<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\{DashboardController, UserController, RoleController, ProfileController, FileController, ApprovalController};
use App\Http\Controllers\{CustomerController, VendorController, VehicleController, BidSheetController, BidController};
use App\Http\Controllers\{BiddingResultController, CostingController, InvoiceController, PaymentController};
use App\Http\Controllers\{ShipmentController, VehicleReassignController, DocumentController, VendorPaymentController,
    ExpenseController, AccountingController, ReportController, ReportHubController, LogViewerController};
use App\Http\Controllers\Reports;

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
    Route::post('customers/{customer}/deposit/approve', [CustomerController::class, 'approveDeposit'])->middleware('permission:customers.approve_deposit')->name('customers.deposit.approve');
    Route::post('customers/{customer}/deposit/reject',  [CustomerController::class, 'rejectDeposit'])->middleware('permission:customers.approve_deposit')->name('customers.deposit.reject');

    Route::resource('vendors', VendorController::class)->except('show')->middleware('permission:vendors');
    Route::resource('vehicles', VehicleController::class)->middleware('permission:vehicle_requirement');
    Route::post("vehicles/{vehicle}/reject-invoice-request", [VehicleController::class, "rejectInvoiceRequest"])->middleware("permission:pending_approvals.index")->name("vehicles.reject_invoice_request");
    Route::post("vehicles/{vehicle}/cancel-invoice-request", [VehicleController::class, "cancelInvoiceRequest"])->middleware("permission:vehicle_requirement.index")->name("vehicles.cancel_invoice_request");
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
    Route::get("vehicles-sold", [VehicleController::class, "sold"])->middleware("permission:vehicle_requirement.index")->name("vehicles.sold");
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
    Route::put('vehicles/{vehicle}/costing', [CostingController::class, 'updateCosting'])->middleware(['permission:costings.edit', 'permission:costings.edit_costs'])->name('costings.update');
    Route::put('vehicles/{vehicle}/selling-price', [CostingController::class, 'updateSellingPrice'])->middleware('permission:costings.edit')->name('costings.selling');

    Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices.index')->name('invoices.index');
    Route::post('vehicles/{vehicle}/invoice', [InvoiceController::class, 'store'])->middleware('permission:invoices.create')->name('invoices.store');
    Route::get('customers/{customer}/invoices/bulk-create', [InvoiceController::class, 'bulkCreateForm'])->middleware('permission:invoices.create')->name('invoices.bulk_create_form');
    Route::post('customers/{customer}/invoices/bulk', [InvoiceController::class, 'bulkStore'])->middleware('permission:invoices.create')->name('invoices.bulk_store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.show')->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:invoices.print')->name('invoices.pdf');
    Route::put('invoices/{invoice}/settle', [InvoiceController::class, 'settle'])->middleware('permission:invoices.adjust_settled_amount')->name('invoices.settle');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:invoices.edit')->name('invoices.cancel');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:invoices.delete')->name('invoices.destroy');
    Route::get('customers/{customer}/invoices/merge', [InvoiceController::class, 'mergeSelectForm'])->middleware('permission:invoices.print')->name('invoices.merge_select');
    Route::post('customers/{customer}/invoices/merge', [InvoiceController::class, 'mergePdf'])->middleware('permission:invoices.print')->name('invoices.merge_pdf');
    Route::post('invoices/merge', [InvoiceController::class, 'mergeSelectedPdf'])->middleware('permission:invoices.print')->name('invoices.merge_selected');
    Route::post('invoices/{invoice}/adjust-deposit', [InvoiceController::class, 'adjustDeposit'])->middleware('permission:invoices.edit')->name('invoices.adjust_deposit');
    Route::get('vehicles/{vehicle}/deposit-invoice/pdf', [VehicleController::class, 'depositInvoicePdf'])->middleware('permission:vehicle_requirement.print')->name('vehicles.deposit_invoice_pdf');
    Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.index')->name('payments.index');
    Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::post('payments/{payment}/approve', [PaymentController::class, 'approve'])->middleware('permission:payments.approve')->name('payments.approve');
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->middleware('permission:payments.approve')->name('payments.reject');
    Route::post('payments/{payment}/undo-approval', [PaymentController::class, 'undoApproval'])->middleware('permission:payments.reverse')->name('payments.undo_approval');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->middleware('permission:payments.edit')->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->middleware('permission:payments.edit')->name('payments.update');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete')->name('payments.destroy');
    Route::post("payments/{payment}/undo-deposit-adjustment", [InvoiceController::class, "undoDepositAdjustment"])->middleware("permission:invoices.edit")->name("payments.undo_deposit_adjustment");
    Route::get('customers/{customer}/ledger', [PaymentController::class, 'customerLedger'])->middleware('permission:payments.index')->name('payments.customer_ledger');

    Route::get('approvals', [ApprovalController::class, 'index'])->middleware('permission:pending_approvals.index')->name('approvals.index');

    Route::get('shipments/new-options', [ShipmentController::class, 'newShipmentOptions'])->middleware('permission:shipments.create')->name('shipments.new_options');
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

    Route::resource('vendor-payments', VendorPaymentController::class)->only(['index', 'store', 'edit'])->middleware('permission:vendor_payments');
    // Editing/deleting a vendor payment always reverses a posted ledger entry, so these two
    // also require the "Reverse/Void Vendor Payment" special permission (enforced again in
    // the controller as the source of truth — this just gives a clean 403 before it gets there).
    Route::put('vendor-payments/{vendor_payment}', [VendorPaymentController::class, 'update'])->middleware(['permission:vendor_payments.edit', 'permission:vendor_payments.reverse'])->name('vendor-payments.update');
    Route::delete('vendor-payments/{vendor_payment}', [VendorPaymentController::class, 'destroy'])->middleware(['permission:vendor_payments.delete', 'permission:vendor_payments.reverse'])->name('vendor-payments.destroy');
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'edit', 'update', 'destroy'])->middleware('permission:expenses');

    // General accounting screens (chart of accounts, journal, ledger, cash/bank book) —
    // the module's own View/View Detail/Export permissions are enough for these.
    Route::middleware('permission:accounting.index')->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('chart',       [AccountingController::class, 'chartOfAccounts'])->name('chart');
        Route::post('chart',      [AccountingController::class, 'storeAccount'])->middleware('permission:accounting.create')->name('chart.store');
        Route::put('chart/{account}', [AccountingController::class, 'updateAccount'])->middleware('permission:accounting.edit')->name('chart.update');
        Route::delete('chart/{account}', [AccountingController::class, 'destroyAccount'])->middleware('permission:accounting.delete')->name('chart.destroy');

        Route::post('heads', [AccountingController::class, 'storeHead'])->middleware('permission:accounting.create')->name('heads.store');
        Route::put('heads/{head}', [AccountingController::class, 'updateHead'])->middleware('permission:accounting.edit')->name('heads.update');
        Route::delete('heads/{head}', [AccountingController::class, 'destroyHead'])->middleware('permission:accounting.delete')->name('heads.destroy');

        Route::post('subheads', [AccountingController::class, 'storeSubhead'])->middleware('permission:accounting.create')->name('subheads.store');
        Route::put('subheads/{subhead}', [AccountingController::class, 'updateSubhead'])->middleware('permission:accounting.edit')->name('subheads.update');
        Route::delete('subheads/{subhead}', [AccountingController::class, 'destroySubhead'])->middleware('permission:accounting.delete')->name('subheads.destroy');

        Route::get('mappings', [AccountingController::class, 'mappings'])->name('mappings');
        Route::put('mappings', [AccountingController::class, 'updateMappings'])->middleware('permission:accounting.edit')->name('mappings.update');
        Route::get('journal',     [AccountingController::class, 'journal'])->name('journal');
        Route::get('ledger/{account}', [AccountingController::class, 'ledger'])->name('ledger');
        Route::get('cash-bank',   [AccountingController::class, 'cashBankBook'])->name('cash_bank');
    });

    // Sensitive financial statements — gated by their own Report Access permissions
    // instead of the blanket accounting.index, per the Role Permission screen's note
    // that "sensitive accounting actions are controlled separately below".
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('receivables',   [AccountingController::class, 'receivables'])->middleware('permission:reports.receivables')->name('receivables');
        Route::get('payables',      [AccountingController::class, 'payables'])->middleware('permission:reports.payables')->name('payables');
        Route::get('trial-balance', [AccountingController::class, 'trialBalance'])->middleware('permission:reports.financial_reports')->name('trial_balance');
        Route::get('balance-sheet', [AccountingController::class, 'balanceSheet'])->middleware('permission:reports.financial_reports')->name('balance_sheet');
        Route::get('profit-loss',   [AccountingController::class, 'profitLoss'])->middleware('permission:reports.financial_reports')->name('profit_loss');
    });

    Route::get("accounting/day-book", [AccountingController::class, "dayBook"])->middleware("permission:accounting.index")->name("accounting.day_book");
    Route::get("accounting/party-ledger", [AccountingController::class, "partyLedger"])->middleware("permission:accounting.index")->name("accounting.party_ledger");
    Route::get("accounting/cash-book", [AccountingController::class, "cashBook"])->middleware("permission:accounting.index")->name("accounting.cash_book");
    Route::get("accounting/bank-book", [AccountingController::class, "bankBook"])->middleware("permission:accounting.index")->name("accounting.bank_book");
    Route::get("accounting/receivables-aging", [AccountingController::class, "receivablesAging"])->middleware("permission:reports.receivables")->name("accounting.receivables_aging");
    Route::get("accounting/expense-analysis", [AccountingController::class, "expenseAnalysis"])->middleware("permission:reports.financial_reports")->name("accounting.expense_analysis");
    Route::get("accounting/cash-flow", [AccountingController::class, "cashFlow"])->middleware("permission:reports.financial_reports")->name("accounting.cash_flow");
    Route::get("accounting/vouchers/{journalEntry}/print", [AccountingController::class, "printVoucher"])->middleware("permission:accounting.index")->name("accounting.voucher_print");

    Route::get('accounting/journal-vouchers/create', [\App\Http\Controllers\JournalVoucherController::class, 'create'])->middleware('permission:accounting.create')->name('accounting.journal_voucher.create');
    Route::post('accounting/journal-vouchers', [\App\Http\Controllers\JournalVoucherController::class, 'store'])->middleware('permission:accounting.create')->name('accounting.journal_voucher.store');
    Route::post('accounting/journal-vouchers/{journalEntry}/reverse', [\App\Http\Controllers\JournalVoucherController::class, 'reverse'])->middleware('permission:accounting.delete')->name('accounting.journal_voucher.reverse');

    // Reports V2 — every report key / permission / label lives in App\Support\ReportCatalog.
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportHubController::class, 'index'])->name('index');

        $r = fn (string $uri, array $action, string $key) => Route::get($uri, $action)->middleware("permission:reports.$key")->name($key);

        // Executive & Management
        $r('executive-summary', [Reports\ExecutiveReportController::class, 'summary'], 'executive_summary');
        $r('business-trends',   [Reports\ExecutiveReportController::class, 'trends'], 'business_trends');
        $r('business-exposure', [Reports\ExecutiveReportController::class, 'exposure'], 'business_exposure');

        // Sales & Customers
        $r('customer-wise',             [Reports\SalesReportController::class, 'customerWise'], 'customer_wise');
        $r('customer-360',              [Reports\SalesReportController::class, 'customer360'], 'customer_360');
        $r('customer-profitability',    [Reports\SalesReportController::class, 'customerProfitability'], 'customer_profitability');
        $r('customer-purchase-history', [Reports\SalesReportController::class, 'purchaseHistory'], 'customer_purchase_history');
        $r('invoice-summary',           [Reports\SalesReportController::class, 'invoiceSummary'], 'invoice_summary');
        $r('inactive-customers',        [Reports\SalesReportController::class, 'inactive'], 'inactive_customers');

        // Auctions & Vehicles
        $r('bid-won',                   [Reports\VehicleReportController::class, 'won'], 'bid_won');
        $r('vehicle-pipeline',          [Reports\VehicleReportController::class, 'pipeline'], 'vehicle_pipeline');
        $r('vehicle-profitability',     [Reports\VehicleReportController::class, 'profitability'], 'vehicle_profitability');
        $r('bid-wise',                  [ReportController::class, 'bidWise'], 'bid_wise');
        $r('auction-house-performance', [ReportController::class, 'auctionHousePerformance'], 'auction_house_performance');

        // Finance & Payments
        $r('receivables-aging',      [Reports\FinanceReportController::class, 'receivablesAging'], 'receivables_aging');
        $r('payment-collections',    [Reports\FinanceReportController::class, 'collections'], 'payment_collections');
        $r('payment-stages',         [Reports\FinanceReportController::class, 'stages'], 'payment_stages');
        $r('security-deposits',      [Reports\FinanceReportController::class, 'deposits'], 'security_deposits');
        $r('vendor-payables',        [Reports\FinanceReportController::class, 'vendorPayables'], 'vendor_payables');
        $r('vendor-payment-history', [Reports\FinanceReportController::class, 'vendorPaymentHistory'], 'vendor_payment_history');
        $r('expense-analysis',       [Reports\FinanceReportController::class, 'expenses'], 'expense_analysis');
        $r('revenue-vs-expense',     [Reports\FinanceReportController::class, 'revenueVsExpense'], 'revenue_vs_expense');
        $r('vendor-wise',            [ReportController::class, 'vendorWise'], 'vendor_wise');

        // Shipping & Logistics
        $r('shipment',          [Reports\ShippingReportController::class, 'overview'], 'shipment');
        $r('ready-for-shipment', [Reports\ShippingReportController::class, 'ready'], 'ready_for_shipment');
        $r('awaiting-shipment', [Reports\ShippingReportController::class, 'awaiting'], 'awaiting_shipment');
        $r('shipping-cost',     [Reports\ShippingReportController::class, 'cost'], 'shipping_cost');

        // Agents & Commissions
        $r('agent-wise',          [Reports\AgentReportController::class, 'performance'], 'agent_wise');
        $r('agent-commission',    [Reports\AgentReportController::class, 'commission'], 'agent_commission');
        $r('agent-monthly',       [Reports\AgentReportController::class, 'monthly'], 'agent_monthly');
        $r('agent-conversion',    [Reports\AgentReportController::class, 'conversion'], 'agent_conversion');
        $r('agent-portfolio',     [Reports\AgentReportController::class, 'portfolio'], 'agent_portfolio');
        $r('agent-leaderboard',   [Reports\AgentReportController::class, 'leaderboard'], 'agent_leaderboard');
        $r('agent-profitability', [ReportController::class, 'agentProfitability'], 'agent_profitability');
    });

    Route::get("customers/{customer}/deposit-invoice/edit", [CustomerController::class, "editDepositInvoice"])->middleware("permission:customers.edit")->name("customers.deposit_invoice.edit");
    Route::put("customers/{customer}/deposit-invoice", [CustomerController::class, "updateDepositInvoice"])->middleware("permission:customers.edit")->name("customers.deposit_invoice.update");
    Route::post("customers/{customer}/generate-deposit-invoice", [CustomerController::class, "generateDepositInvoice"])->middleware("permission:customers.edit")->name("customers.generate_deposit_invoice");
    Route::get('system/logs', [LogViewerController::class, 'index'])->middleware('permission:system.logs')->name('system.logs');
    Route::get('system/logs/{file}/download', [LogViewerController::class, 'download'])->middleware('permission:system.logs')->name('system.logs.download');
});