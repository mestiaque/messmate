# MessMate User Guide

MessMate runs a shared mess's meals, money and month-end settlement. This guide follows a typical month.

## Who does what

| Role | Can |
|---|---|
| **Organization owner** | Everything in every mess of the organization, plus billing and support |
| **Mess admin** | Configure the mess (members, roles, meal types, settings) |
| **Manager** | Enter meals, bazar, expenses and deposits; approve records; run the settlement |
| **Accountant / cashier** | Finance screens and approvals (as granted) |
| **Member** | See their own meals, balance and statements; enter their own meals if self-service is on |

One person can have different roles in different messes.

## 1. Getting started

1. **Register** and log in with your email or phone. "Forgot password?" on the login page sends a reset link by email.
2. **Create an organization.** A trial starts automatically.
3. **Create a mess.** Choose the currency, timezone, period mode (calendar month or custom dates) and meal tracking mode.
4. **Add members.** Use **Members → Add**. Add someone without a login account (for example a cook), or **invite** them by email or phone. An invitation link only works for the email or phone it was sent to.

## 2. Every day: meals

- **Meals → Daily entry**:
  - In *individual* mode, tick each meal (breakfast 0.5, lunch 1, dinner 1 by default; change this in **Meal types**).
  - In *daily total* mode, enter the number of units.
- Tick **Approve** to approve while saving, or approve later in **Meals → Approvals**.
- Guests go under **Meals → Guests** and adjustments (for example a skipped meal) under **Meals → Adjustments**. Both need a reason and an approval.
- Members can see their own calendar. If **Portal settings → Self-service meals** is on, they can enter their own meals under **My portal → My meals**.

## 3. Money

- **Bazar**: record each market trip as a total or item by item, with an optional receipt photo or PDF (up to 5 MB). Mark which part is food. Only food goes into the meal rate.
- **Expenses**: rent, gas, salaries and so on. Choose how each expense is shared: equally, by meals, custom amounts, by usage, or excluded from members.
- **Deposits**: money members hand over. The same reference cannot be recorded twice.
- Nothing counts until it is **approved**. To correct an approved record, **reverse** it. History is never edited.
- **Finance → Ledger** shows every posting. **Balances** must always say "Ledger balanced".

## 4. Month end: settlement

1. Make sure everything for the period is approved.
2. **Settlement → Preview** shows each member's meals, share of food and other expenses, deposits and the balance due or refundable.
3. **Generate draft**, check it, then **Finalize**. Finalizing:
   - freezes the numbers,
   - posts the charges to the ledger,
   - locks the period.
4. Record what members pay or what you refund under **Settlement → Payments**.
5. If a mistake is found after finalizing, **Reopen**. This reverses the charges and keeps the old version for audit. Then fix the records and finalize again.

## 5. Dashboard, reports and portal

- The **dashboard** shows the period at a glance: meals, food spend, estimated meal rate and trends.
- **Reports** has eight reports (daily meals, monthly meals by member, bazar, expenses, deposits, settlement, outstanding balances and member ledger). Each can be printed. CSV export depends on your plan.
- **My portal** shows each member their meals today, estimated bill, balance, statements and announcements.

## 6. Billing and support

- **Billing** shows your plan, its limits and invoices.
  - Pay by bank or bKash transfer and enter the transaction reference. A platform admin confirms it.
  - If a subscription lapses, the organization becomes **read-only**. Nothing is deleted. Billing and support stay usable.
- **Support** opens a ticket with the platform team.

## Tips

- Use one browser tab per mess. The URL shows which organization and mess you are in.
- Amounts are stored to 2 decimal places, and rounding remainders are shared fairly so totals always add up.
