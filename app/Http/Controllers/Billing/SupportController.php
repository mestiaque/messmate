<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenant-side support tickets (owner only).
 */
class SupportController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);

        return view('support.index', ['tenant' => $tenant, 'tickets' => $tenant->supportTickets()->latest('updated_at')->paginate(20)]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);
        $data = $request->validate(['subject' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:5000']]);

        $ticket = $tenant->supportTickets()->create(['user_id' => $request->user()->id, 'subject' => $data['subject']]);
        $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return redirect()->route('support.show', ['supportTicket' => $ticket])->with('status', 'Ticket opened. We will reply here.');
    }

    public function show(Tenant $tenant, SupportTicket $supportTicket): View
    {
        $this->authorize('update', $tenant);

        return view('support.show', ['tenant' => $tenant, 'ticket' => $supportTicket->load('messages.user'), 'replyRoute' => route('support.reply', ['supportTicket' => $supportTicket])]);
    }

    public function reply(Request $request, Tenant $tenant, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('update', $tenant);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $supportTicket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $supportTicket->update(['status' => 'open']);

        return back()->with('status', 'Reply sent.');
    }
}
