<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email', 'phone']);

        // Optional fields may be omitted entirely; only a submitted change resets verification.
        if (array_key_exists('email', $data) && $user->email !== $data['email']) {
            $user->email_verified_at = null;
        }

        if (array_key_exists('phone', $data) && $user->phone !== $data['phone']) {
            $user->phone_verified_at = null;
        }

        $user->fill($data);

        if ($request->filled('password')) {
            $user->password = $request->input('password');
        }

        $user->save();

        return back()->with('status', 'Profile updated.');
    }
}
