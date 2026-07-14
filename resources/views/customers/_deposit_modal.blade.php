<div id="depositModal" class="modal-block modal-block-success mfp-hide">
    <section class="card">
        <form method="POST" id="depositForm" action="" onkeydown="return event.key != 'Enter';">
            @csrf
            <header class="card-header"><h2 class="card-title">Security Deposit — <span id="deposit_customer_name"></span></h2></header>
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
                <p class="text-muted small mb-0">This is refundable and will be posted as a liability until returned or applied.</p>
            </div>
            <footer class="card-footer">
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-success">Record Deposit</button>
                    <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </footer>
        </form>
    </section>
</div>

<script>
function openDeposit(id, name) {
    $('#depositForm').attr('action', '/customers/' + id + '/deposit');
    $('#deposit_customer_name').text(name);
    $.magnificPopup.open({ items: { src: '#depositModal' }, type: 'inline' });
}
</script>