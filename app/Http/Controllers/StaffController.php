<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $staff = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', User::ROLE_STAFF)
            ->with('outlet:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'outlet_id' => $s->outlet_id,
                'outlet_name' => $s->outlet?->name ?? '-',
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        $outlets = Outlet::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Outlet $o) => [
                'id' => $o->id,
                'name' => $o->name,
            ]);

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
        ]);

        $user = auth()->user();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_STAFF,
            'tenant_id' => $user->tenant_id,
            'outlet_id' => $data['outlet_id'],
        ]);

        return redirect()->route('staff.index')->with('success', 'Staff berhasil ditambahkan.');
    }

    public function update(Request $request, User $staff)
    {
        $this->authorizeStaff($staff);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $staff->id],
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
        ]);

        $staff->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'outlet_id' => $data['outlet_id'],
        ]);

        return redirect()->route('staff.index')->with('success', 'Staff berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $staff)
    {
        $this->authorizeStaff($staff);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $staff->update([
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('staff.index')->with('success', 'Password staff berhasil direset.');
    }

    public function destroy(User $staff)
    {
        $this->authorizeStaff($staff);

        $staff->delete();

        return redirect()->route('staff.index')->with('success', 'Staff berhasil dihapus.');
    }

    private function authorizeStaff(User $staff): void
    {
        $user = auth()->user();
        if ($staff->tenant_id !== $user->tenant_id || $staff->role !== User::ROLE_STAFF) {
            abort(403, 'Unauthorized.');
        }
    }
}
