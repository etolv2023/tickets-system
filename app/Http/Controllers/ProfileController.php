<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-08-19) F26.1 — "my account", for the handful of facts that are the
 * person's to give rather than the admin's to assign.
 *
 * Right now that is one field: the Discord id an exception ticket mentions.
 * The page exists because nobody else can supply it — an admin does not know
 * somebody's Discord snowflake, and asking ten people to send it over so one
 * person can type it in is a worse process than a form field.
 *
 * No route parameter anywhere on this controller, on purpose. The user being
 * edited is always $request->user(), so there is no id to tamper with and no
 * IDOR to defend against — the shape of the route is the authorisation.
 * (An admin editing somebody ELSE's id is a different screen with a different
 * permission: /admin/users, behind users.manage.)
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()
            ->route('profile.edit')
            ->with('status', 'اتحفظ.');
    }
}
