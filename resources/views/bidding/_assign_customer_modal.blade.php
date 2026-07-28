@can('bid_sheets.edit')
<div id="assignCustomerModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <form method="POST" id="assignCustomerForm" action="" onkeydown="return event.key != 'Enter';">
            @csrf @method('PUT')
            <header class="card-header"><h2 class="card-title">Assign Customer — Lot <span id="assign_lot_no"></span></h2></header>
            <div class="card-body">
                <div class="row form-group">
                    <div class="col-lg-12 mb-2">
                        <label>Customer <span class="text-danger">*</span></label>
                        <select data-plugin-selecttwo class="form-control select2-js" name="customer_id" required>
                            <option value="" disabled selected>Select customer</option>
                            @forelse($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @empty
                                <option value="" disabled>No customers with a completed profile yet</option>
                            @endforelse
                        </select>
                        <small class="text-muted">Only customers with a completed profile are listed — bidding requires a completed profile.</small>
                    </div>
                </div>
            </div>
            <footer class="card-footer">
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">Assign</button>
                    <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </footer>
        </form>
    </section>
</div>

<script>
function openAssignCustomer(bidId, lotNo) {
    document.getElementById('assignCustomerForm').action = '/bids/' + bidId + '/assign-customer';
    document.getElementById('assign_lot_no').textContent = lotNo || bidId;
    $.magnificPopup.open({ items: { src: '#assignCustomerModal' }, type: 'inline' });
}
</script>
@endcan