@php $actionLabels = ['index'=>'View','show'=>'View Detail','create'=>'Create','edit'=>'Edit','delete'=>'Delete','print'=>'Export/Print']; @endphp

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="mb-3">
    <label>Role Name <span class="text-danger">*</span></label>
    <input type="text" class="form-control" name="name" value="{{ old('name', $role->name) }}" style="max-width:400px;" required>
</div>

<h6 class="text-muted text-uppercase small mt-4 mb-2">Module Permissions</h6>
<div class="table-responsive mb-4">
    <table class="table table-bordered table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Module</th>
                @foreach($actionLabels as $action => $label)
                    <th class="text-center">{{ $label }}<br><a href="#" class="small toggle-column" data-action="{{ $action }}">toggle all</a></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($moduleMatrix as $module => $permissions)
            <tr>
                <td><strong>{{ \Illuminate\Support\Str::headline($module) }}</strong><br><a href="#" class="small toggle-row" data-module="{{ $module }}">toggle row</a></td>
                @foreach($actionLabels as $action => $label)
                    @php $perm = $permissions->firstWhere('name', "$module.$action"); @endphp
                    <td class="text-center">
                        @if($perm)
                            <input type="checkbox" class="form-check-input perm-checkbox module-{{ $module }} action-{{ $action }}"
                                   name="permissions[]" value="{{ $perm->id }}" {{ in_array($perm->id, $assigned) ? 'checked' : '' }}>
                        @endif
                    </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h6 class="text-muted text-uppercase small mb-2">Report Access</h6>
<div class="row mb-4">
    @foreach($reportPermissions as $perm)
    <div class="col-md-3 mb-2">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}" {{ in_array($perm->id, $assigned) ? 'checked' : '' }}>
            <label class="form-check-label" for="perm_{{ $perm->id }}">{{ \Illuminate\Support\Str::headline(str($perm->name)->after('reports.')) }}</label>
        </div>
    </div>
    @endforeach
</div>

<h6 class="text-muted text-uppercase small mb-2">Special / Business-Logic Permissions</h6>
<div class="row mb-4">
    @foreach($specialPermissions as $name => $description)
        @php $perm = \Spatie\Permission\Models\Permission::where('name', $name)->first(); @endphp
        @if($perm)
        <div class="col-md-6 mb-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}" {{ in_array($perm->id, $assigned) ? 'checked' : '' }}>
                <label class="form-check-label" for="perm_{{ $perm->id }}"><code>{{ $name }}</code><br><small class="text-muted">{{ $description }}</small></label>
            </div>
        </div>
        @endif
    @endforeach
</div>

<button type="submit" class="btn btn-primary">Save Role</button>
<a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>

<script>
document.querySelectorAll('.toggle-row').forEach(el => el.addEventListener('click', function(e) {
    e.preventDefault();
    const boxes = document.querySelectorAll('.module-' + this.dataset.module);
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}));
document.querySelectorAll('.toggle-column').forEach(el => el.addEventListener('click', function(e) {
    e.preventDefault();
    const boxes = document.querySelectorAll('.action-' + this.dataset.action);
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}));
</script>