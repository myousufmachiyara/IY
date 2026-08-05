<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\{Role, Permission};

class RoleController extends Controller
{
    /** Canonical module sequence — must be kept in sync with DatabaseSeeder's $modules array. */
    private array $moduleOrder = [
        'members', 'roles', 'customers', 'vehicle_requirement', 'vendors',
        'bid_sheets', 'merge_bids', 'bid_results', 'invoices', 'shipments',
        'payments', 'vendor_payments', 'expenses', 'pending_approvals',
        'accounting', 'costings', 'documents',
    ];

    private array $specialPermissions = [
        'data.view_all'              => 'See all data across every agent — bypasses the scoping below entirely.',
        'scope.by_agent'             => 'Scope customers, vehicles, and bids to this user as the owning Sales Agent. Also reveals sales commission fields on the Team form.',
        'finance.backdate'           => 'Allow approving customer deposits and payments, plus recording back-dated payments/expenses.',
        'customers.assign_any_agent' => 'Allow assigning a customer or vehicle to any agent, not just themselves.',
        'system.logs'                => 'View and download raw application error logs — technical/debugging access only.',
        'invoices.request'           => 'Allow a Sales Agent to request an invoice for a won vehicle, without granting full invoice creation rights.',
        'dates.future'               => 'Removes the "auction date must be tomorrow or later" restriction on Bid Sheet uploads — holder may enter any date.',
    ];

    public function index()
    {
        $roles = Role::withCount('permissions', 'users')->orderBy('name')->get();
        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create', [
            'role'               => new Role,
            'assigned'           => [],
            'moduleMatrix'       => $this->moduleMatrix(),
            'reportPermissions'  => $this->reportPermissions(),
            'specialPermissions' => $this->specialPermissions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);
        $this->flushPermissionCache();

        return redirect()->route('roles.index')->with('success', 'Role created — it takes effect immediately, no cache clear needed.');
    }

    public function edit(Role $role)
    {
        return view('roles.edit', [
            'role'               => $role,
            'assigned'           => $role->permissions->pluck('id')->toArray(),
            'moduleMatrix'       => $this->moduleMatrix(),
            'reportPermissions'  => $this->reportPermissions(),
            'specialPermissions' => $this->specialPermissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->name === 'super_admin', 403, 'The Super Admin role cannot be modified.');

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions'   => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update(['name' => $data['name']]);
        $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);
        $this->flushPermissionCache();

        return redirect()->route('roles.index')->with('success', 'Role updated — changes take effect immediately.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->name === 'super_admin', 403, 'The Super Admin role cannot be deleted.');
        abort_if($role->users()->exists(), 422, 'Cannot delete a role currently assigned to users. Reassign those users first.');

        $role->delete();
        $this->flushPermissionCache();

        return back()->with('success', 'Role deleted.');
    }

    private function flushPermissionCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Grouped by module, reordered to follow $moduleOrder regardless of each permission row's actual DB insertion order. */
    private function moduleMatrix()
    {
        $special = array_keys($this->specialPermissions);

        $grouped = Permission::where('name', 'not like', 'reports.%')
            ->whereNotIn('name', $special)
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        $ordered = collect($this->moduleOrder)
            ->filter(fn ($m) => $grouped->has($m))
            ->mapWithKeys(fn ($m) => [$m => $grouped[$m]]);

        // Anything unexpected (a leftover custom permission not in the canonical list) still shows up, just at the end.
        return $ordered->merge($grouped->except($ordered->keys()));
    }

    private function reportPermissions()
    {
        return Permission::where('name', 'like', 'reports.%')->get();
    }
}