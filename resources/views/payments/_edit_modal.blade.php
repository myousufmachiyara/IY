@can('payments.edit')
<div id="editPaymentModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <form method="POST" id="editPaymentForm" action="" onkeydown="return event.key != 'Enter';">
            @csrf @method('PUT')
            <header class="card-header"><h2 class="card-title">Edit Payment</h2></header>
            <div class="card-body">
                <div class="row form-group">
                    <div class="col-lg-6 mb-2">
                        <label>Amount (¥) <span class="text-danger">*</span></label>
                        <input type="number" id="edit_pay_amount" class="form-control" name="amount" min="1" required>
                    </div>
                    <div class="col-lg-6 mb-2">
                        <label>Paid Into <span class="text-danger">*</span></label>
                                @include('partials.money_account', ['name' => 'account_id', 'id' => 'edit_pay_account'])
                    </div>
                    <div class="col-lg-6 mb-2">
                        <label>Date Paid <span class="text-danger">*</span></label>
                        <input type="date" id="edit_pay_date" class="form-control" name="paid_at" required>
                    </div>
                    <div class="col-lg-6 mb-2">
                        <label>Reference</label>
                        <input type="text" id="edit_pay_reference" class="form-control" name="reference">
                    </div>
                </div>
                <p class="text-muted small mb-0"><i class="fa fa-info-circle"></i> Editing reverses the original ledger entry and posts a fresh one, preserving the audit trail.</p>
            </div>
            <footer class="card-footer">
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">Update Payment</button>
                    <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </footer>
        </form>
    </section>
</div>

<script>
function editPayment(id, amount, accountId, paidAt, reference) {
    document.getElementById('editPaymentForm').action = '/payments/' + id;
    document.getElementById('edit_pay_amount').value = amount;
    document.getElementById('edit_pay_date').value = paidAt;
    document.getElementById('edit_pay_reference').value = reference || '';
    if (accountId) $('#edit_pay_account').val(accountId);
    $.magnificPopup.open({ items: { src: '#editPaymentModal' }, type: 'inline' });
}
</script>
@endcan