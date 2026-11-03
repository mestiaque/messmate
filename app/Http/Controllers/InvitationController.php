<?php

namespace App\Http\Controllers;

use App\Services\Tenancy\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function show(string $token): View
    {
        $invitation = $this->memberships->findInvitation($token);
        abort_unless($invitation, 404);

        return view('invitations.show', ['invitation' => $invitation->load('mess.tenant', 'role'), 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->memberships->findInvitation($token);
        abort_unless($invitation, 404);

        $member = $this->memberships->acceptInvitation($invitation, $request->user());

        return redirect()->route('mess.dashboard', ['tenant' => $member->mess->tenant, 'mess' => $member->mess])
            ->with('status', "Welcome to {$member->mess->name}.");
    }
}
