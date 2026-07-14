<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\{Role, Permission};

class RoleController extends Controller
{
    private array $specialPermissions = [
        'data.view_all'              => 'See all data across every agent and vendor — bypasses the scoping below entirely.',
        'scope.by_agent'             => 'Scope customers, vehicles, and bids to this user as the owning Sales Agent. Also reveals sales commission fields on the Team form.',
        'scope.by_vendor'            => 'Scope vehicles to this user as the supplying Vendor Agent. Also reveals vendor commission fields on the Team form.',
        'finance.backdate'           => 'Allow recording back-dated payments, vendor payments, and expenses.',
        'customers.assign_any_agent' => 'Allow assigning a customer or vehicle to any agent, not just themselves.',
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

        // Resolve to actual Permission models before syncing — passing raw string IDs
        // straight from the form can make some spatie/laravel-permission versions try
        // to resolve them BY NAME instead of by ID, throwing PermissionDoesNotExist.
        $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);

        return redirect()->route('roles.index')->with('success', 'Role created.');
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
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions'   => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update(['name' => $data['name']]);

        $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->users()->exists(), 422, 'Cannot delete a role currently assigned to users. Reassign those users first.');
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    private function moduleMatrix()
    {
        $special = array_keys($this->specialPermissions);

        return Permission::where('name', 'not like', 'reports.%')
            ->whereNotIn('name', $special)
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);
    }

    private function reportPermissions()
    {
        return Permission::where('name', 'like', 'reports.%')->get();
    }
}