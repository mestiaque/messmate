# Business Rules

## Access (Phase 1)

- A user may enter a tenant if they own it or have an active `tenant_users` row, and the tenant is active.
- A user may enter a mess if they own its tenant, or hold an **active** membership in that mess.
  Left/inactive/suspended memberships lose access immediately.
- Mess permissions come from the member's roles. Tenant owners hold every mess permission in their tenant.
- Requests for resources outside the user's reach return 404, not 403, to avoid revealing they exist.
- `tenant_id` / `mess_id` are never taken from request input; they come from the URL-resolved, verified context.
- Only users with `roles.manage` may grant roles other than Member; members cannot change their own roles
  (tenant owner excepted).
- A user can have at most one active membership per mess.
- Platform admins manage tenants but have no implicit access to tenant data.

## System mess roles

| Role | Permissions |
|---|---|
| Mess Administrator | all |
| Manager | mess.view, members.*, periods.manage, meal_types.manage, meals.view/manage/approve |
| Accountant | mess.view, members.view, meals.view |
| Bazar Manager | mess.view, members.view, meals.view |
| Member | mess.view |

(Accountant and Bazar Manager gain module permissions in Phases 3–4.)

## Periods and meals (Phase 2)

- Periods in a mess never overlap. Only an **open** period accepts new or edited meals, guest meals and adjustments.
  Approvals are possible while open or reviewing. Finalizing requires no submitted (pending) items.
- Reopening (finalized/locked → open) needs `periods.reopen` (Mess Administrator / owner), a reason, and is audited.
- A member can eat on a date only if `joined_at <= date <= left_at` (left_at open-ended) and the status is active or
  left. Inactive, suspended and invited members get no meals.
- Leaving needs a left date; it is refused if non-voided meals exist after that date. A left membership is not
  reactivated; the person is added again as a new membership.
- One live meal entry per member per date. Its mode (individual / daily total) cannot change unless the entry is voided.
- Individual: `weighted_units = quantity × unit_factor_snapshot`, factor copied at save time. Changing a meal type's
  factor never changes saved entries.
- Daily total: units entered directly, or `raw_count × conversion_factor` with both stored.
- Entries by a user with `meals.approve` may be approved on save; otherwise they are submitted.
  Editing an approved or rejected entry resets it to submitted (audited as a correction).
- Guest meal units = quantity × factor (meal type factor, or 1). Charges are stored separately from units and are
  not yet posted anywhere (Phase 3/4 decide how).
- Arithmetic uses bcmath strings, 4 decimals for units, half-up rounding.

## Bazar, expenses, deposits and ledger (Phase 3)

- **Single source per purchase:** bazar purchases live only in `bazar_entries`; they are never copied into
  `expenses`. Manual food expenses are entered as expenses with a Food-type category. The Phase 4 food pool =
  Σ approved `bazar_entries.food_amount` + Σ approved food-category expenses (+ approved food adjustments).
- Amounts are always recomputed on the server; browser totals are ignored.
- Itemized line total = round(qty × unit price, 2) − line discount. Entry total = Σ lines − entry discount.
  Food share = food lines' share of the subtotal applied to the discounted total (half-up, 2 dp).
- Posting on approval:
  | Event | Debit | Credit |
  |---|---|---|
  | Bazar | Food expense (food share), Other expenses (rest) | Cash / Bank / Wallet / Member balance (purchaser) / Vendor payables |
  | Expense | Food expense or Other expenses | same choice of payment source |
  | Deposit | Cash / Bank / Wallet | Member balance (member) |
- A member's balance on "Member balances" is credit − debit: positive means the mess owes the member.
- Only submitted items can be approved or rejected; approval is allowed in open or reviewing periods.
  Voiding (and deposit reversal) needs an open period and posts a reversal; nothing posted is edited or deleted.
- Approved items cannot be edited — void and re-enter.
- Food expenses must use meal-based allocation. Custom shares must equal the amount exactly; usage-based shares
  are proportional with leftover paisa going to the largest remainders.
- Bank and mobile-wallet deposits need a transaction ID; a reference can be used once per mess and method.
- Receipts: jpg/png/webp/pdf ≤ 5 MB, private disk, served only to users with the module's permission.

## Settlement (Phase 4)

- **Food pool** = Σ approved bazar `food_amount` + Σ approved Food-category expenses in the period.
- **Units** = approved meal entries (saved factor snapshots) + approved meal adjustments per member; approved guest
  units go to the sponsor; unsponsored guest units are in the denominator and their cost is shared equally by
  eligible members as a separate statement line.
- **Strategies:** weighted (rate = pool ÷ total units; pool split by largest remainder so Σ = pool exactly),
  equal (pool ÷ eligible members; guests not charged — warned), custom fixed rate (difference reported as
  unrecovered/surplus, never hidden).
- Food cost with zero units blocks finalization; zero food gives rate 0.
- **Eligibility** uses effective dates: a member is eligible if `joined_at ≤ period end` and
  `left_at` is empty or `≥ period start`. Equal splits are per member or per day present (setting).
- **Other expenses:** equal / meal-based (by member units) / custom & usage (saved shares) / excluded (listed, not
  charged). The non-food part of bazar purchases is split equally.
- **Closing credit** = opening + deposits + other credits − (meals + guests + other expenses) − other debits.
  Other credits: purchases/expenses the member paid, credit adjustments, settlement collections in the period.
  Other debits: debit adjustments, refunds in the period. Positive = mess owes member.
- **Opening credit** = member balance in the ledger on the day before the period starts.
- **Finalize** requires the period in review, no pending items, and no existing final version; it posts
  Dr Member balance / Cr Settlement recoveries for each member's charges, then checks every member's ledger balance
  at period end equals the closing credit, the food expense account movement equals the pool, and the ledger
  balances. Any failure → nothing is saved.
- **Reopen** (needs `periods.reopen` + reason): final snapshot → superseded (unchanged), its ledger charges reversed
  (dated at period end), period back to open. Re-finalizing creates the next version.
- **Payments:** direction comes from the settlement sign; amount ≤ outstanding; status partially_paid / paid.

## Portal and reports (Phase 5)

- Dashboard and portal figures come from the database; the meal rate shown before settlement is an estimate from
  approved records.
- Self-service meals are off by default. When on, members enter only their own meals (never by member id),
  only in open periods, and cannot change a day once approved. With approval required, entries are submitted.
- Report totals for bazar, expenses and deposits include approved records only.
- Members may open their own ledger report; all other finance reports need `finance.view`, meal reports `meals.view`.
- CSV cells that begin with `= + - @` (non-numeric) are prefixed with `'`.

## Subscriptions (Phase 6)

- Full access while: trial not ended; active and period end + grace not passed; past-due within grace;
  cancelled before period end. Otherwise read-only. Data is never deleted for billing reasons.
- Read-only blocks every non-GET request in an organization except billing and support.
- Limits (NULL = unlimited) are checked before creating a mess, adding a non-left member, or bringing a new
  user into the organization. A plan change is refused if current usage exceeds it.
- Prices always come from the plan. A payment is applied once; later callbacks for it are ignored.
  A callback must carry a valid signature, be under 10 minutes old, and match the payment's amount and currency.
- When paid time remains, a new period is added after it (no proration); the plan itself changes immediately.

Financial and calculation rules: see `masterprompt.md` Part 5; they are recorded here as each phase implements them.
