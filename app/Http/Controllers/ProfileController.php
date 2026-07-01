<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form (password change only).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit');
    }
}
