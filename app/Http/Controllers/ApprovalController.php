<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Payment, Vehicle};

class ApprovalController extends Controller
{
    public function index()
    {
        $pendingDeposits = Customer::where('security_deposit_status', 'pending')->with('depositReceivedBy')->get();
        $pendingPayments = Payment::where('status', 'pending')->with('customer', 'invoice', 'recorder')->get();
        $pendingInvoiceRequests = Vehicle::whereNotNull('invoice_requested_at')->with('customer', 'agent')->latest('invoice_requested_at')->get();

        return view('approvals.index', compact('pendingDeposits', 'pendingPayments', 'pendingInvoiceRequests'));
    }
}