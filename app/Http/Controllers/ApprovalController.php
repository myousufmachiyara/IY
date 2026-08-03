<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Payment};

class ApprovalController extends Controller
{
    public function index()
    {
        $pendingDeposits = Customer::where('security_deposit_status', 'pending')
            ->with('agent', 'depositReceivedBy')
            ->latest('security_deposit_received_at')
            ->get();

        $pendingPayments = Payment::where('status', 'pending')
            ->with('customer', 'invoice', 'recorder')
            ->latest('paid_at')
            ->get();

        return view('approvals.index', compact('pendingDeposits', 'pendingPayments'));
    }
}