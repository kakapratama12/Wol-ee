<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $members = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_CASHIER])
            ->with('branch:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'branch_id' => $u->branch_id,
                'branch_name' => $u->branch?->name,
            ]);

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Settings/Team/Index', [
            'members' => $members,
            'branches' => $branches,
            'roles' => [
                User::ROLE_STAFF => 'Staff',
                User::ROLE_CASHIER => 'Kasir',
            ],
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'tenant_id' => $request->user()->tenant_id,
            'branch_id' => $data['role'] === User::ROLE_CASHIER ? $data['branch_id'] : null,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Anggota tim ditambahkan.');
    }

    public function update(UpdateTeamMemberRequest $request, User $user): RedirectResponse
    {
        $request->authorizeMember();

        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'role' => $data['role'],
            'branch_id' => $data['role'] === User::ROLE_CASHIER ? $data['branch_id'] : null,
        ]);

        return back()->with('success', 'Anggota tim diperbarui.');
    }
}
