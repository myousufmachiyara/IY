<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // DataTables handles paging/search/sort client-side.
        $users = User::with('roles')->latest()->get();
        return view('team.index', compact('users'));
    }

    public function store(UserRequest $request)
    {
        $user = User::create($this->payload($request) + ['created_by' => $request->user()->id]);
        $user->syncRoles($request->role);

        return back()->with('success', 'Team member created.');
    }

    /** Modal edit form fetches this as JSON. */
    public function edit(User $team)
    {
        $team->load('roles');

        return response()->json([
            ...$team->toArray(),
            'role' => $team->roles->first()?->name,
        ]);
    }

    public function update(UserRequest $request, User $team)
    {
        $team->update($this->payload($request));
        $team->syncRoles($request->role);

        return back()->with('success', 'Team member updated.');
    }

    public function destroy(User $team)
    {
        abort_if($team->isSuperAdmin() && User::role('super_admin')->count() === 1, 403, 'Cannot delete the last super admin.');
        $team->delete();

        return back()->with('success', 'Team member removed.');
    }

    private function payload(UserRequest $request): array
    {
        $data = $request->safe()->except(['role', 'password', 'password_confirmation']);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        if ($request->role !== 'sales_agent') {
            $data['sales_commission_percent'] = null;
            $data['sales_fixed_bonus'] = null;
        }
        if ($request->role !== 'vendor_agent') {
            $data['vendor_commission_percent'] = null;
            $data['vendor_location'] = null;
        }

        return $data;
    }
}