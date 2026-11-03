<?php

namespace Tests\Feature\Meals;

use App\Models\MealEntry;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_bulk_saves_and_approves_daily_meals(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $manager = $this->memberOf($mess, Role::MANAGER);
        $a = $this->memberOf($mess, Role::MEMBER);
        $b = $this->memberOf($mess, Role::MEMBER);
        $t = $this->mealTypeIds($mess);
        $date = $period->start_date->toDateString();
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        $this->actingAs($manager->user)->get("{$base}/meals/daily?date={$date}")->assertOk()->assertSee($a->full_name);

        $this->actingAs($manager->user)->post("{$base}/meals/daily", [
            'date' => $date, 'tracking_mode' => 'individual', 'approve' => '1',
            'selected' => [$a->id, $b->id],
            'meals' => [
                $a->id => [$t['breakfast'] => 1, $t['lunch'] => 1, $t['dinner'] => 1],
                $b->id => [$t['breakfast'] => 0, $t['lunch'] => 1, $t['dinner'] => 0],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('2.5000', MealEntry::where('member_id', $a->id)->value('total_units'));
        $this->assertSame('1.0000', MealEntry::where('member_id', $b->id)->value('total_units'));
        $this->assertSame(2, MealEntry::where('status', 'approved')->count());
    }

    public function test_bulk_save_is_all_or_nothing_and_rejects_foreign_members(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        [$other] = $this->messWithOpenPeriod();
        $a = $this->memberOf($mess, Role::MEMBER);
        $foreign = $this->memberOf($other, Role::MEMBER);
        $date = $period->start_date->toDateString();
        $url = "/t/{$mess->tenant->slug}/m/{$mess->slug}/meals/daily";
        $owner = $mess->tenant->owner;

        $this->actingAs($owner)->post($url, [
            'date' => $date, 'tracking_mode' => 'daily_total', 'selected' => [$a->id, $foreign->id],
            'units' => [$a->id => 2, $foreign->id => 2],
        ])->assertSessionHasErrors('selected');

        $late = $this->memberOf($mess, Role::MEMBER);
        $late->update(['joined_at' => $period->end_date]);
        $this->actingAs($owner)->post($url, [
            'date' => $date, 'tracking_mode' => 'daily_total', 'selected' => [$a->id, $late->id],
            'units' => [$a->id => 2, $late->id => 2],
        ])->assertSessionHasErrors('member');

        $this->assertSame(0, MealEntry::count());
    }

    public function test_plain_member_cannot_enter_meals_and_sees_only_own_calendar(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $other = $this->memberOf($mess, Role::MEMBER);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        $this->actingAs($member->user)->get("{$base}/meals/daily")->assertForbidden();
        $this->actingAs($member->user)->post("{$base}/meals/daily", [
            'date' => $period->start_date->toDateString(), 'tracking_mode' => 'daily_total', 'selected' => [$member->id], 'units' => [$member->id => 5],
        ])->assertForbidden();
        $this->actingAs($member->user)->get("{$base}/meals/approvals")->assertForbidden();

        $this->actingAs($member->user)->get("{$base}/meals/calendar?member={$other->id}")
            ->assertOk()->assertSee($member->full_name)->assertDontSee($other->full_name);
        $this->actingAs($member->user)->get("{$base}/members/{$member->id}")->assertOk();
        $this->actingAs($member->user)->get("{$base}/members/{$other->id}")->assertForbidden();
    }

    public function test_approval_screen_and_bulk_approve(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $owner = $mess->tenant->owner;
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";
        $date = $period->start_date->toDateString();

        $this->actingAs($owner)->post("{$base}/meals/daily", [
            'date' => $date, 'tracking_mode' => 'daily_total', 'selected' => [$member->id], 'units' => [$member->id => 2], 'approve' => '0',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("{$base}/meals/guests", ['guest_name' => 'Uncle', 'meal_date' => $date, 'quantity' => 1, 'approve' => '0'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post("{$base}/meals/adjustments", ['period_id' => $period->id, 'member_id' => $member->id, 'adjustment_units' => '-0.5', 'reason' => 'Skipped dinner', 'approve' => '0'])->assertSessionHasNoErrors();

        $this->actingAs($owner)->get("{$base}/meals/approvals")->assertOk()->assertSee('Uncle')->assertSee('Skipped dinner');

        $entry = $mess->mealEntries()->first();
        $guest = $mess->guestMeals()->first();
        $adj = $mess->mealAdjustments()->first();
        $this->actingAs($owner)->post("{$base}/meals/approvals/bulk", ['ids' => ["meal-entries:{$entry->id}", "guest-meals:{$guest->id}", "adjustments:{$adj->id}"]])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $entry->fresh()->status->value);
        $this->assertSame('approved', $guest->fresh()->status->value);
        $this->assertSame('approved', $adj->fresh()->status->value);
    }

    public function test_records_of_another_mess_cannot_be_approved(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$other, $otherPeriod] = $this->messWithOpenPeriod();
        $foreignMember = $this->memberOf($other, Role::MEMBER);
        $foreign = MealEntry::create([
            'tenant_id' => $other->tenant_id, 'mess_id' => $other->id, 'period_id' => $otherPeriod->id, 'member_id' => $foreignMember->id,
            'meal_date' => $otherPeriod->start_date, 'tracking_mode' => 'daily_total', 'total_units' => 1, 'status' => 'submitted',
        ]);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";

        $this->actingAs($mess->tenant->owner)->post("{$base}/meals/approvals/meal-entries/{$foreign->id}", ['action' => 'approve'])->assertNotFound();
        $this->actingAs($mess->tenant->owner)->post("{$base}/meals/approvals/bulk", ['ids' => ["meal-entries:{$foreign->id}"]])->assertNotFound();
        $this->assertSame('submitted', $foreign->fresh()->status->value);
    }

    public function test_meal_types_can_be_configured(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        $admin = $this->memberOf($mess, Role::MESS_ADMIN);
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}/meal-types";

        $this->actingAs($admin->user)->get($base)->assertOk()->assertSee('Breakfast');
        $this->actingAs($admin->user)->post($base, ['name' => 'Special', 'code' => 'special', 'unit_factor' => '1.5', 'default_enabled' => '0', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin->user)->post($base, ['name' => 'Dup', 'code' => 'lunch', 'unit_factor' => '1', 'default_enabled' => '0', 'is_active' => '1'])
            ->assertSessionHasErrors('code');

        $this->assertSame('1.500000', $mess->mealTypes()->where('code', 'special')->value('unit_factor'));
    }

    public function test_every_meal_screen_renders(): void
    {
        [$mess, $period] = $this->messWithOpenPeriod();
        $member = $this->memberOf($mess, Role::MEMBER);
        $owner = $mess->tenant->owner;
        $base = "/t/{$mess->tenant->slug}/m/{$mess->slug}";
        $date = $period->start_date->toDateString();

        // Populate rows so per-row partials (void buttons, statuses) render too.
        $this->actingAs($owner)->post("{$base}/meals/daily", ['date' => $date, 'tracking_mode' => 'daily_total', 'selected' => [$member->id], 'units' => [$member->id => 2]]);
        $this->actingAs($owner)->post("{$base}/meals/guests", ['guest_name' => 'Guest', 'meal_date' => $date, 'quantity' => 1]);
        $this->actingAs($owner)->post("{$base}/meals/adjustments", ['period_id' => $period->id, 'member_id' => $member->id, 'adjustment_units' => '1', 'reason' => 'Test']);

        foreach (["meals/daily?date={$date}", 'meals/daily?mode=daily_total', 'meals/calendar', "meals/calendar?member={$member->id}", 'meals/guests', 'meals/adjustments', 'meals/approvals', 'periods', 'meal-types', 'members', 'members/create', "members/{$member->id}", "members/{$member->id}/edit"] as $path) {
            $this->actingAs($owner)->get("{$base}/{$path}")->assertOk();
        }
    }
}
