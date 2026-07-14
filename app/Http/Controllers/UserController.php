<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->latest()->get();
        $roles = Role::with('permissions')->orderBy('name')->get();

        $rolePermMap = $roles->mapWithKeys(fn ($r) => [$r->name => [
            'agent'  => $r->permissions->contains('name', 'scope.by_agent'),
            'vendor' => $r->permissions->contains('name', 'scope.by_vendor'),
        ]]);

        return view('team.index', compact('users', 'roles', 'rolePermMap'));
    }

    public function store(UserRequest $request)
    {
        $user = User::create($this->payload($request) + ['created_by' => $request->user()->id]);
        $user->syncRoles($request->role);

        return back()->with('success', 'Team member created.');
    }

    public function edit(User $team)
    {
        $team->load('roles');

        return response()->json([...$team->toArray(), 'role' => $team->roles->first()?->name]);
    }

    public function update(UserRequest $request, User $team)
    {
        $team->update($this->payload($request));
        $team->syncRoles($request->role);

        return back()->with('success', 'Team member updated.');
    }

    public function destroy(User $team)
    {
        abort_if(
            $team->isSuperAdmin() && User::permission('user_roles.edit')->count() === 1,
            403,
            'Cannot delete the last user who can manage roles & permissions.'
        );
        $team->delete();

        return back()->with('success', 'Team member removed.');
    }

    private function payload(UserRequest $request): array
    {
        $data = $request->safe()->except(['role', 'password', 'password_confirmation']);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        // Which commission fields persist is driven by the SELECTED ROLE's actual
        // permissions, not a hardcoded role name.
        $role = Role::where('name', $request->role)->first();

        if (! $role?->hasPermissionTo('scope.by_agent')) {
            $data['sales_commission_percent'] = null;
            $data['sales_fixed_bonus'] = null;
        }
        if (! $role?->hasPermissionTo('scope.by_vendor')) {
            $data['vendor_commission_percent'] = null;
            $data['vendor_location'] = null;
        }

        return $data;
    }
}