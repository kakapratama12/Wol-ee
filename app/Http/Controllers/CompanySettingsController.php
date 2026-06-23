<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanySettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Settings/Company', [
            'tenant' => [
                'name' => $tenant->name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'bank_name' => $tenant->bank_name,
                'bank_account' => $tenant->bank_account,
                'bank_account_name' => $tenant->bank_account_name,
            ],
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $tenant->update($request->validated());

        return back()->with('success', 'Pengaturan perusahaan berhasil disimpan.');
    }
}
