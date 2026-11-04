<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAnnouncement;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformSupportController extends Controller
{
    public function tickets(Request $request): View
    {
        return view('platform.tickets', [
            'tickets' => SupportTicket::query()->with('tenant:id,name', 'user:id,name')
                ->when($request->query('status', 'open'), fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
                ->latest('updated_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('support.show', ['tenant' => $ticket->tenant, 'ticket' => $ticket->load('messages.user'), 'replyRoute' => route('platform.tickets.reply', $ticket), 'staff' => true]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'status' => ['required', Rule::in(['answered', 'closed'])]]);

        $ticket->messages()->create(['user_id' => $request->user()->id, 'is_staff' => true, 'body' => $data['body']]);
        $ticket->update(['status' => $data['status']]);

        return back()->with('status', 'Reply sent.');
    }

    public function announcements(): View
    {
        return view('platform.announcements', ['announcements' => PlatformAnnouncement::query()->latest('published_at')->paginate(20)]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:5000'], 'expires_at' => ['nullable', 'date', 'after:now']]);
        PlatformAnnouncement::create($data + ['published_at' => now(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'Announcement published to all organizations.');
    }

    public function withdrawAnnouncement(PlatformAnnouncement $announcement): RedirectResponse
    {
        $announcement->update(['expires_at' => now()]);

        return back()->with('status', 'Announcement withdrawn.');
    }
}
