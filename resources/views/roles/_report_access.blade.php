{{-- Report Access: one collapsible card per category; the round checkbox on the card
     selects/clears every report in it (indeterminate when only some are ticked). --}}
@php $totalReports = collect($reportPermissions)->sum(fn ($c) => count($c['reports'])); @endphp

<div class="report-access mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="card-title mb-0">Report Access</h2>
        <div class="small d-flex align-items-center gap-2">
            <span class="ra-summary text-muted"><span class="ra-total-selected">0</span> of {{ $totalReports }} reports</span>
            <a href="#" class="ra-select-all">Select all</a>
            <span class="text-muted">·</span>
            <a href="#" class="ra-clear-all">Clear</a>
            <span class="text-muted">·</span>
            <a href="#" class="ra-expand-all">Expand all</a>
        </div>
    </div>
    <p class="text-muted small mt-1 mb-3">Select the report categories this role can access. Expand a category to manage individual reports.</p>

    @foreach($reportPermissions as $catKey => $cat)
        @php $anyChecked = collect($cat['reports'])->contains(fn ($r) => in_array($r['permission']->id, $assigned)); @endphp
        <div class="ra-cat" data-cat="{{ $catKey }}">
            <div class="ra-head">
                <label class="ra-check mb-0" title="Select / clear all {{ $cat['label'] }} reports">
                    <input type="checkbox" class="ra-cat-toggle">
                    <span class="ra-circle"></span>
                </label>
                <button type="button" class="ra-toggle-body" aria-expanded="false">
                    <span class="ra-title">{{ $cat['label'] }}</span>
                    <span class="ra-count"><span class="ra-selected">0</span> of {{ count($cat['reports']) }} reports</span>
                    <i class="fas fa-chevron-down ra-chevron"></i>
                </button>
            </div>
            <div class="ra-body" hidden>
                @foreach($cat['reports'] as $r)
                    <label class="ra-item">
                        <span class="ra-check">
                            <input type="checkbox" class="ra-report" name="permissions[]" value="{{ $r['permission']->id }}" {{ in_array($r['permission']->id, $assigned) ? 'checked' : '' }}>
                            <span class="ra-circle"></span>
                        </span>
                        <span>
                            <span class="ra-item-label">{{ $r['label'] }}</span>
                            <span class="ra-item-desc">{{ $r['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<style>
    .report-access { max-width: 760px; }
    .ra-cat { border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 10px; background: #fff; overflow: hidden; }
    .ra-head { display: flex; align-items: center; gap: 12px; padding: 12px 16px; }
    .ra-toggle-body { flex: 1; display: grid; grid-template-columns: 1fr auto; grid-template-rows: auto auto; text-align: left; border: 0; background: none; padding: 0; cursor: pointer; }
    .ra-title { font-weight: 600; font-size: 14px; color: #111827; grid-column: 1; }
    .ra-count { font-size: 12px; color: #6b7280; grid-column: 1; }
    .ra-chevron { grid-column: 2; grid-row: 1 / span 2; align-self: center; color: #6b7280; transition: transform .15s; }
    .ra-cat.open .ra-chevron { transform: rotate(180deg); }
    .ra-body { border-top: 1px solid #f0f0f0; padding: 6px 16px 10px; }
    .ra-item { display: flex; gap: 12px; align-items: flex-start; padding: 7px 0; margin: 0; cursor: pointer; }
    .ra-item-label { display: block; font-size: 14px; color: #111827; }
    .ra-item-desc { display: block; font-size: 11px; color: #9ca3af; }

    .ra-check { position: relative; display: inline-flex; flex: 0 0 auto; cursor: pointer; }
    .ra-check input { position: absolute; opacity: 0; width: 0; height: 0; }
    .ra-circle { width: 20px; height: 20px; border-radius: 50%; border: 1.5px solid #c4c7cc; background: #fff; display: inline-flex; align-items: center; justify-content: center; transition: all .12s; }
    .ra-check input:checked + .ra-circle,
    .ra-check input:indeterminate + .ra-circle { background: #111; border-color: #111; }
    .ra-check input:checked + .ra-circle::after { content: ''; width: 5px; height: 9px; border: solid #fff; border-width: 0 2px 2px 0; transform: translateY(-1px) rotate(45deg); }
    .ra-check input:indeterminate + .ra-circle::after { content: ''; width: 9px; height: 2px; background: #fff; border-radius: 1px; }
    .ra-check input:focus-visible + .ra-circle { box-shadow: 0 0 0 3px rgba(37, 99, 235, .3); }
</style>

<script>
(function () {
    const root = document.querySelector('.report-access');
    if (!root) return;

    function refresh() {
        let total = 0;
        root.querySelectorAll('.ra-cat').forEach(cat => {
            const boxes = [...cat.querySelectorAll('.ra-report')];
            const n = boxes.filter(b => b.checked).length;
            total += n;
            cat.querySelector('.ra-selected').textContent = n;
            const toggle = cat.querySelector('.ra-cat-toggle');
            toggle.checked = n === boxes.length && n > 0;
            toggle.indeterminate = n > 0 && n < boxes.length;
        });
        root.querySelector('.ra-total-selected').textContent = total;
    }

    function setOpen(cat, open) {
        cat.classList.toggle('open', open);
        cat.querySelector('.ra-body').hidden = !open;
        cat.querySelector('.ra-toggle-body').setAttribute('aria-expanded', open);
    }

    root.querySelectorAll('.ra-cat').forEach(cat => {
        cat.querySelector('.ra-toggle-body').addEventListener('click', () => setOpen(cat, !cat.classList.contains('open')));
        cat.querySelector('.ra-cat-toggle').addEventListener('change', function () {
            cat.querySelectorAll('.ra-report').forEach(b => b.checked = this.checked);
            refresh();
        });
        cat.querySelectorAll('.ra-report').forEach(b => b.addEventListener('change', refresh));
        // Open partially-selected categories so admins see what is ticked.
        const boxes = [...cat.querySelectorAll('.ra-report')];
        const n = boxes.filter(b => b.checked).length;
        if (n > 0 && n < boxes.length) setOpen(cat, true);
    });

    root.querySelector('.ra-select-all').addEventListener('click', e => { e.preventDefault(); root.querySelectorAll('.ra-report').forEach(b => b.checked = true); refresh(); });
    root.querySelector('.ra-clear-all').addEventListener('click', e => { e.preventDefault(); root.querySelectorAll('.ra-report').forEach(b => b.checked = false); refresh(); });
    root.querySelector('.ra-expand-all').addEventListener('click', function (e) {
        e.preventDefault();
        const cats = [...root.querySelectorAll('.ra-cat')];
        const open = !cats.every(c => c.classList.contains('open'));
        cats.forEach(c => setOpen(c, open));
        this.textContent = open ? 'Collapse all' : 'Expand all';
    });

    refresh();
})();
</script>
