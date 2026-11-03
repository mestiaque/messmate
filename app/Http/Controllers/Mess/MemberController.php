<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Enums\MembershipStatus;
use App\Http\Requests\Mess\AddMemberRequest;
use App\Http\Requests\Mess\InviteMemberRequest;
use App\Http\Requests\Mess\StoreMemberRequest;
use App\Http\Requests\Mess\SyncMemberRolesRequest;
use App\Http\Requests\Mess\UpdateMemberRequest;
use App\Models\Mess;
use App\Models\MessInvitation;
use App\Models\MessMember;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Meals\MealSummaryService;
use App\Services\Periods\AccountingPeriodService;
use App\Services\Tenancy\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $this->authorize('viewMembers', $mess);

        $members = $mess->members()
            ->with('roles:id,name', 'user:id,name')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('member_code', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('member_code')
            ->paginate(25)
            ->withQueryString();

        return view('members.index', [
            'mess' => $mess,
            'members' => $members,
            'roles' => Role::query()->assignableInMess($mess)->orderBy('id')->get(['id', 'name', 'slug']),
            'canManage' => $request->user()->can('manageMembers', $mess),
            'canManageRoles' => $request->user()->can('manageRoles', $mess),
            'invitations' => $mess->invitations()->where('status', 'pending')->where('expires_at', '>', now())->with('role:id,name')->latest()->get(),
        ]);
    }

    public function store(AddMemberRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $login = $request->string('login')->trim()->value();
        $user = User::query()->where(filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone', $login)->first();

        if (! $user) {
            return back()->withInput()->withErrors(['login' => 'No registered user has this email or phone. Send them an invitation instead, or add them without an account.']);
        }

        $this->memberships->addUser($mess, $user, $request->input('roles'), $request->user(), $request->safe()->only(['room_number', 'joined_at']));

        return back()->with('status', "{$user->name} added to {$mess->name}.");
    }

    public function syncRoles(SyncMemberRolesRequest $request, Tenant $tenant, Mess $mess, MessMember $member): RedirectResponse
    {
        if ($member->user_id === $request->user()->id && $tenant->owner_id !== $request->user()->id) {
            return back()->withErrors(['roles' => 'You cannot change your own roles.']);
        }

        $this->memberships->syncRoles($member, $request->input('roles'), $request->user());

        return back()->with('status', "Roles updated for {$member->full_name}.");
    }

    public function create(Tenant $tenant, Mess $mess): View
    {
        $this->authorize('manageMembers', $mess);

        return view('members.create', [
            'mess' => $mess,
            'roles' => Role::query()->assignableInMess($mess)->orderBy('id')->get(['id', 'name', 'slug']),
            'canManageRoles' => request()->user()->can('manageRoles', $mess),
        ]);
    }

    public function storeWithoutAccount(StoreMemberRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $member = $this->memberships->createWithoutAccount($mess, $request->safe()->except('roles'), $request->input('roles'), $request->user());

        return redirect()->route('members.show', ['member' => $member])->with('status', "{$member->full_name} added as {$member->member_code}.");
    }

    /**
     * Member profile with meal history and period statement.
     */
    public function show(Request $request, Tenant $tenant, Mess $mess, MessMember $member, AccountingPeriodService $periods, MealSummaryService $summary): View
    {
        $this->authorize('view', $member);

        $period = $request->filled('period')
            ? $mess->periods()->findOrFail($request->integer('period'))
            : ($periods->periodFor($mess, now($mess->timezone)) ?? $mess->periods()->latest('start_date')->first());

        return view('members.show', [
            'mess' => $mess,
            'member' => $member->load('roles:id,name', 'user:id,name,email,phone'),
            'period' => $period,
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'entries' => $period ? $period->mealEntries()->where('member_id', $member->id)->with('items.mealType:id,name')->orderBy('meal_date')->get() : collect(),
            'adjustments' => $period ? $period->mealAdjustments()->where('member_id', $member->id)->latest()->get() : collect(),
            'totals' => $period ? $summary->memberPeriod($member, $period) : null,
            'canManage' => $request->user()->can('update', $member),
            'settlements' => $member->settlements()->whereIn('status', ['finalized', 'partially_paid', 'paid'])->with('period:id,name')->latest('id')->get(),
        ]);
    }

    public function edit(Tenant $tenant, Mess $mess, MessMember $member): View
    {
        $this->authorize('update', $member);

        return view('members.edit', compact('mess', 'member'));
    }

    public function update(UpdateMemberRequest $request, Tenant $tenant, Mess $mess, MessMember $member): RedirectResponse
    {
        $this->memberships->update($member, $request->validated());

        return redirect()->route('members.show', ['member' => $member])->with('status', 'Member updated.');
    }

    public function changeStatus(Request $request, Tenant $tenant, Mess $mess, MessMember $member): RedirectResponse
    {
        $this->authorize('update', $member);
        $data = $request->validate([
            'status' => ['required', Rule::in([MembershipStatus::Active->value, MembershipStatus::Inactive->value, MembershipStatus::Suspended->value, MembershipStatus::Left->value])],
            'left_at' => ['required_if:status,left', 'nullable', 'date'],
        ]);

        if ($member->user_id === $request->user()->id && $data['status'] !== MembershipStatus::Active->value && $tenant->owner_id !== $request->user()->id) {
            return back()->withErrors(['status' => 'You cannot deactivate your own membership.']);
        }

        $this->memberships->changeStatus($member, MembershipStatus::from($data['status']), $data['left_at'] ?? null, $request->user());

        return back()->with('status', "Status changed to {$data['status']}.");
    }

    public function linkUser(Request $request, Tenant $tenant, Mess $mess, MessMember $member): RedirectResponse
    {
        $this->authorize('update', $member);
        $login = trim($request->validate(['login' => ['required', 'string', 'max:255']])['login']);
        $user = User::query()->where(filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone', $login)->first();

        if (! $user) {
            return back()->withErrors(['login' => 'No registered user has this email or phone.']);
        }

        $this->memberships->linkUser($member, $user, $request->user());

        return back()->with('status', "Linked to {$user->name}'s account.");
    }

    public function invite(InviteMemberRequest $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        [, $token] = $this->memberships->invite($mess, $request->input('email'), $request->input('phone'), $request->integer('role_id'), $request->user());

        // The plain token exists only now; show the link once so it can be shared.
        return back()->with('status', 'Invitation created. Share this link (valid 7 days, shown once):')
            ->with('invitation_link', route('invitations.show', $token));
    }

    public function revokeInvitation(Tenant $tenant, Mess $mess, MessInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $mess);
        $this->memberships->revokeInvitation($invitation);

        return back()->with('status', 'Invitation revoked.');
    }
}
