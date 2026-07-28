{{-- ================= RECEIVE DEPOSIT MODAL (Agent) ================= --}}
<div id="receiveDepositModal" class="modal-block modal-block-success mfp-hide">
    <section class="card">
        <form method="POST" id="receiveDepositForm" action="" onkeydown="return event.key != 'Enter';">
            @csrf
            <header class="card-header"><h2 class="card-title">Record Deposit Received — <span id="receive_deposit_customer_name"></span></h2></header>
            <div class="card-body">
                <div class="row form-group">
                    <div class="col-lg-6 mb-2">
                        <label>Deposit Amount (¥) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="security_deposit" min="1" required>
                    </div>
                    <div class="col-lg-6 mb-2">
                        <label>Received Into <span class="text-danger">*</span></label>
                        <select class="form-control select2-js" name="account" required>
                            <option value="1000">Cash</option>
                            <option value="1010" selected>Bank</option>
                        </select>
                    </div>
                </div>
                <p class="text-muted small mb-0">This marks the deposit as received and sends it for accountant approval — it is not posted to the ledger until approved.</p>
            </div>
            <footer class="card-footer">
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-success">Submit for Approval</button>
                    <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </footer>
        </form>
    </section>
</div>

{{-- ================= REJECT DEPOSIT MODAL (Accountant/Admin) ================= --}}
@can('customers.edit')
<div id="rejectDepositModal" class="modal-block modal-block-danger mfp-hide">
    <section class="card">
        <form method="POST" id="rejectDepositForm" action="" onkeydown="return event.key != 'Enter';">
            @csrf
            <header class="card-header"><h2 class="card-title">Reject Deposit — <span id="reject_deposit_customer_name"></span></h2></header>
            <div class="card-body">
                <label>Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="security_deposit_rejection_reason" rows="3" required></textarea>
            </div>
            <footer class="card-footer">
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-danger">Reject</button>
                    <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </footer>
        </form>
    </section>
</div>
@endcan

<script>
function openReceiveDeposit(id, name) {
    document.getElementById('receiveDepositForm').action = '/customers/' + id + '/deposit/receive';
    document.getElementById('receive_deposit_customer_name').textContent = name;
    $.magnificPopup.open({ items: { src: '#receiveDepositModal' }, type: 'inline' });
}

function openRejectDeposit(id, name) {
    document.getElementById('rejectDepositForm').action = '/customers/' + id + '/deposit/reject';
    document.getElementById('reject_deposit_customer_name').textContent = name;
    $.magnificPopup.open({ items: { src: '#rejectDepositModal' }, type: 'inline' });
}
</script>