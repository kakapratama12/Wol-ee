<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanySettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'slug' => $tenant->slug,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'bank_name' => $tenant->bank_name,
                'bank_account' => $tenant->bank_account,
                'bank_account_name' => $tenant->bank_account_name,
                'logo' => $tenant->logo,
                'logo_url' => $tenant->logo ? '/storage/logos/' . $tenant->id . '/' . $tenant->logo : null,
            ],
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $data = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($tenant->logo && Storage::disk('public')->exists('logos/' . $tenant->id . '/' . $tenant->logo)) {
                Storage::disk('public')->delete('logos/' . $tenant->id . '/' . $tenant->logo);
            }

            $file = $request->file('logo');
            $filename = 'logo.' . $file->getClientOriginalExtension();
            $file->storeAs('logos/' . $tenant->id, $filename, 'public');
            $data['logo'] = $filename;
        }

        // Handle logo removal
        if ($request->input('remove_logo') === '1') {
            if ($tenant->logo && Storage::disk('public')->exists('logos/' . $tenant->id . '/' . $tenant->logo)) {
                Storage::disk('public')->delete('logos/' . $tenant->id . '/' . $tenant->logo);
            }
            $data['logo'] = null;
        }

        unset($data['remove_logo']);
        $tenant->update($data);

        return back()->with('success', 'Pengaturan perusahaan berhasil disimpan.');
    }
}
