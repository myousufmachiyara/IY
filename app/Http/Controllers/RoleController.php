<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\{Role, Permission};

class RoleController extends Controller
{
    /**
     * Order (and set) of modules shown on the main permission grid — mirrors the
     * "IY Auto Trades — Create/Edit Role Permission Screen" spec row-for-row.
     * Audit Log is intentionally not included yet (no activity-log backend exists
     * in this codebase to gate) — add it here once that feature lands.
     */
    private array $moduleOrder = [
        'members', 'roles', 'customers', 'vehicle_requirement', 'vendors',
        'bid_sheets', 'merge_bids', 'bid_results', 'costings', 'invoices',
        'payments', 'vendor_payments', 'expenses', 'shipments', 'documents',
        'pending_approvals', 'accounting',
    ];

    /**
     * Non-CRUD checkboxes shown below the module grid and the Report Access
     * section — approvals, reversals, sensitive edits, and data-scope switches.
     * Grouped here in the same order as the spec's "Data Scope & Business
     * Permissions" panel; the two data-scope entries (scope.by_agent /
     * data.view_all) behave as mutually exclusive in the create/edit blade.
     */
    private array $specialPermissions = [
        // data scope — pick at most one (enforced client-side as radio-style via
        // the 'scope_group' flag); leaving both unchecked restricts the holder to
        // their own records (see App\Models\Scopes\AgentScope)
        'scope.by_agent' => [
            'label' => 'Scope: Own Records Only (Sales Agent)',
            'description' => 'Scope customers, vehicles, and bids to this user as the owning Sales Agent. Also reveals sales commission fields on the Team form.',
            'scope_group' => true,
        ],
        'data.view_all' => [
            'label' => 'Scope: All Records',
            'description' => 'See all data across every agent — bypasses the scoping above entirely.',
            'scope_group' => true,
        ],
        'customers.assign_any_agent' => [
            'label' => 'Assign Customers/Vehicles to Any Agent',
            'description' => 'Allow assigning a customer or vehicle to any agent, not just themselves.',
        ],

        // approvals
        'payments.approve' => [
            'label' => 'Approve Customer Payments',
            'description' => 'Approve or reject payments recorded against customer invoices.',
        ],
        'customers.approve_deposit' => [
            'label' => 'Approve Security Deposits',
            'description' => 'Approve or reject customer security deposits before they become confirmed funds.',
        ],

        // reversals — void with reason, never a hard delete
        'payments.reverse' => [
            'label' => 'Reverse/Void Customer Payment',
            'description' => 'Reverse an approved customer payment (undo approval) with an audit trail; never hard-deletes it.',
        ],
        'vendor_payments.reverse' => [
            'label' => 'Reverse/Void Vendor Payment',
            'description' => 'Reverse a posted vendor payment while retaining its history.',
        ],

        // sensitive financial edits
        'costings.edit_costs' => [
            'label' => 'Edit Vehicle Costing',
            'description' => 'Change vendor commission, service charge, inland, auction, freight and miscellaneous vehicle costs (the company-cost side of the Costing screen).',
        ],
        'invoices.adjust_settled_amount' => [
            'label' => 'Adjust Settled Amount',
            'description' => 'Make an authorised adjustment to an invoice\'s settled amount after issue; maintain audit trail.',
        ],

        // dates
        'finance.backdate' => [
            'label' => 'Finance Backdate',
            'description' => 'Allow recording payments, vendor payments, deposits and expenses with a date other than today.',
        ],
        'dates.future' => [
            'label' => 'Allow Future Auction Dates',
            'description' => 'Removes the "auction date must be tomorrow or later" restriction on Bid Sheet uploads — holder may enter any date.',
        ],

        // misc
        'invoices.request' => [
            'label' => 'Request Invoice (Sales Agent)',
            'description' => 'Allow a Sales Agent to request an invoice for a won vehicle, without granting full invoice creation rights.',
        ],
        'system.logs' => [
            'label' => 'View System Logs',
            'description' => 'View and download raw application error logs — technical/debugging access only.',
        ],
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
            ->groupBy(fn ($p) => explode('.', $p->name)[0])
            // Eloquent Collection's except()/getDictionary() assume every item is a
            // Model and call ->getKey() on it — but groupBy() nests per-module
            // Collections-of-permissions here, not individual models. Dropping to a
            // plain base Collection makes except() do simple array-key exclusion
            // instead, which is what this actually needs.
            ->toBase();

        $ordered = collect($this->moduleOrder)
            ->filter(fn ($m) => $grouped->has($m))
            ->mapWithKeys(fn ($m) => [$m => $grouped[$m]]);

        return $ordered->merge($grouped->except($ordered->keys()));
    }

    private function reportPermissions()
    {
        return Permission::where('name', 'like', 'reports.%')->get();
    }
}