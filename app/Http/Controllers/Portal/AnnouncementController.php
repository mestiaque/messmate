<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\MessAnnouncement;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Portal\PortalSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Tenant $tenant, Mess $mess, PortalSettingsService $settings): View
    {
        $this->authorize('manageAnnouncements', $mess);

        return view('portal.announcements', [
            'mess' => $mess,
            'announcements' => $mess->announcements()->with('author:id,name')->latest('published_at')->paginate(20),
            'settings' => $settings->get($mess),
        ]);
    }

    public function store(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('manageAnnouncements', $mess);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $announcement = $mess->announcements()->create($data + ['tenant_id' => $mess->tenant_id, 'published_at' => now(), 'created_by' => $request->user()->id]);
        $this->audit->log('announcement.published', $announcement, new: ['title' => $data['title']]);

        return back()->with('status', 'Announcement published.');
    }

    public function destroy(Tenant $tenant, Mess $mess, MessAnnouncement $announcement): RedirectResponse
    {
        $this->authorize('manageAnnouncements', $mess);
        $announcement->update(['expires_at' => now()]);
        $this->audit->log('announcement.withdrawn', $announcement);

        return back()->with('status', 'Announcement withdrawn.');
    }

    public function settings(Request $request, Tenant $tenant, Mess $mess, PortalSettingsService $settings): RedirectResponse
    {
        $this->authorize('manageAnnouncements', $mess);
        $settings->save($mess, $request->boolean('self_service_meals'), $request->boolean('require_approval'));

        return back()->with('status', 'Member portal settings saved.');
    }
}
