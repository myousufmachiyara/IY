<div id="receiveDepositModal" class="modal-block modal-block-success mfp-hide">
    <section class="card">
        <form method="POST" id="receiveDepositForm" action="" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
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
                    <div class="col-lg-12 mb-2">
                        <label>Evidence (receipt / transfer screenshot) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="evidence" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                </div>
                <p class="text-muted small mb-0">This is not posted to the ledger until an accountant approves it.</p>
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

@can('customers.edit')
<div id="editDepositModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <form method="POST" id="editDepositForm" action="" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
            @csrf @method('PUT')
            <header class="card-header"><h2 class="card-title">Edit Deposit</h2></header>
            <div class="card-body">
                <div class="row form-group">
                    <div class="col-lg-6 mb-2">
                        <label>Deposit Amount (¥) <span class="text-danger">*</span></label>
                        <input type="number" id="edit_dep_amount" class="form-control" name="security_deposit" min="1" required>
                    </div>
                    <div class="col-lg-6 mb-2">
                        <label>Received Into <span class="text-danger">*</span></label>
                        <select id="edit_dep_account" class="form-control select2-js" name="account" required>
                            <option value="1000">Cash</option>
                            <option value="1010">Bank</option>
                        </select>
                    </div>
                    <div class="col-lg-12 mb-2">
                        <label>Replace Evidence <small class="text-muted">(optional)</small></label>
                        <input type="file" class="form-control" name="evidence" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>
                <p class="text-muted small mb-0">Editing resets this deposit to "Pending Approval".</p>
            </div>
            <footer class="card-footer">
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </footer>
        </form>
    </section>
</div>
@endcan

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
function openEditDeposit(id) {
    fetch('/customers/' + id + '/deposit/edit').then(r => r.json()).then(data => {
        document.getElementById('editDepositForm').action = '/customers/' + id + '/deposit';
        document.getElementById('edit_dep_amount').value = data.security_deposit;
        $('#edit_dep_account').val(data.security_deposit_account).trigger('change');
        $.magnificPopup.open({ items: { src: '#editDepositModal' }, type: 'inline' });
    });
}
function openRejectDeposit(id, name) {
    document.getElementById('rejectDepositForm').action = '/customers/' + id + '/deposit/reject';
    document.getElementById('reject_deposit_customer_name').textContent = name;
    $.magnificPopup.open({ items: { src: '#rejectDepositModal' }, type: 'inline' });
}
</script>