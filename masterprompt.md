# MessMate SaaS
## Final Technical Specification & Claude Code Development Blueprint

**Version:** 1.0  
**Product:** Multi-Tenant Mess Management SaaS  
**Backend:** Laravel + PHP  
**Frontend:** Blade + Bootstrap 5  
**Database:** MySQL 8  
**Architecture:** Modular Monolith  
**Target:** Bangladesh initially, international market later

---

# PART 1 — FINAL ARCHITECTURE

## 1. Product Requirements

Build a production-ready multi-tenant SaaS application for managing multiple mess organizations.

Each tenant can create multiple messes. Each mess has its own members, accounting periods, meals, bazar entries, expenses, deposits, ledger, settlements and reports.

A user can belong to multiple tenants and multiple messes with different roles.

### Main Modules

1. SaaS Platform Administration
2. Tenant Management
3. Subscription & Billing
4. Mess Management
5. Member Management
6. Roles & Permissions
7. Accounting Period Management
8. Meal Type Configuration
9. Individual Meal Tracking
10. Daily Total Meal Tracking
11. Guest Meal Management
12. Bazar Management
13. Itemized Grocery Management
14. Expense Management
15. Member Deposits
16. Financial Ledger
17. Meal Calculation Engine
18. Expense Allocation
19. Monthly Settlement
20. Member Portal
21. Reports & Analytics
22. Notifications
23. Audit Logs
24. System Settings

---

# PART 2 — DATABASE SCHEMA

## 2. Database Conventions

- Use BIGINT UNSIGNED primary keys.
- Use foreign keys wherever appropriate.
- Use DECIMAL(15,2) for monetary values.
- Use DECIMAL(12,4) for meal units and quantities.
- Use DECIMAL(12,6) for calculation factors and rates where needed.
- Use DATE for accounting dates.
- Use DATETIME for timestamps.
- Use JSON only for genuinely flexible configuration or snapshots.
- Use ENUM or constrained strings consistently.
- Use soft deletes only where business rules permit.
- Never use floating-point types for financial calculations.
- Every tenant-owned resource must have enforceable tenant ownership.
- Every mess-owned resource must have a mess_id.
- Use database transactions for financial operations.

All monetary calculations must use decimal-safe arithmetic.

## 3. SaaS & Authentication Tables

### users

- id
- name
- email (nullable, unique when present)
- phone (nullable, unique when present)
- password
- email_verified_at
- phone_verified_at
- profile_photo_path
- status (active, suspended, inactive)
- last_login_at
- remember_token
- timestamps

### tenants

- id
- owner_id (FK users)
- name
- slug (unique)
- email
- phone
- address
- logo_path
- status (active, suspended, inactive)
- default_currency
- default_timezone
- trial_ends_at
- timestamps
- softDeletes

### tenant_users

- id
- tenant_id
- user_id
- status
- joined_at
- timestamps

Unique: tenant_id + user_id.

### subscription_plans

- id
- name
- slug
- description
- monthly_price
- yearly_price
- currency
- max_messes
- max_members_per_mess
- max_users
- trial_days
- features (JSON)
- limits (JSON)
- is_active
- sort_order
- timestamps

### subscriptions

- id
- tenant_id
- plan_id
- billing_cycle (monthly, yearly)
- status (trial, active, past_due, cancelled, expired)
- starts_at
- trial_ends_at
- current_period_start
- current_period_end
- cancelled_at
- grace_ends_at
- timestamps

### subscription_payments

- id
- subscription_id
- tenant_id
- amount
- currency
- payment_method
- transaction_reference
- provider_reference
- status
- paid_at
- gateway_response (JSON)
- timestamps

### invoices

- id
- tenant_id
- subscription_id
- invoice_number
- subtotal
- discount
- tax
- total
- currency
- status
- issued_at
- due_at
- paid_at
- timestamps

### platform_settings

- id
- key (unique)
- value (JSON or text)
- timestamps

---

# 4. Mess & Membership Tables

### messes

- id
- tenant_id
- name
- slug
- description
- address
- contact_phone
- contact_email
- currency
- timezone
- default_period_mode (calendar, custom)
- default_meal_tracking_mode (individual, daily_total)
- status
- created_by
- timestamps
- softDeletes

Unique: tenant_id + slug.

### mess_members

This is the central membership entity.

- id
- tenant_id
- mess_id
- user_id (nullable for members without accounts)
- member_code
- full_name
- phone
- email
- profile_photo_path
- room_number
- joined_at
- left_at (nullable)
- membership_status (invited, active, inactive, left, suspended)
- notes
- created_by
- timestamps
- softDeletes

Indexes:
- tenant_id + mess_id
- mess_id + user_id
- mess_id + membership_status
- mess_id + joined_at

A member may have only one active membership in a specific mess.

### mess_invitations

- id
- tenant_id
- mess_id
- email
- phone
- invitation_token_hash
- role_id (nullable)
- invited_by
- expires_at
- accepted_at
- status
- timestamps

### roles

- id
- tenant_id (nullable for platform-level roles)
- name
- slug
- guard_name
- scope (platform, tenant, mess)
- timestamps

### permissions

- id
- name
- guard_name
- module
- description
- timestamps

### role_permissions

- role_id
- permission_id

### mess_member_roles

- id
- mess_id
- member_id
- role_id
- assigned_by
- timestamps

Use unique constraints to prevent duplicate assignments.

Use policies and server-side authorization in addition to role checks.

---

# 5. Accounting Period Tables

### accounting_periods

- id
- tenant_id
- mess_id
- name
- period_type (calendar, custom)
- start_date
- end_date
- status (draft, open, reviewing, finalized, locked, archived)
- opened_by
- finalized_by
- finalized_at
- locked_at
- notes
- timestamps

Rules:
- A period cannot have end_date before start_date.
- Accounting periods in the same mess must not overlap.
- A mess can have multiple historical periods.
- Only one open period should cover a particular transaction date.
- Finalized periods cannot receive normal edits.
- Reopening requires explicit authorization and an audit record.

### meal_types

- id
- tenant_id
- mess_id
- name
- code
- unit_factor DECIMAL(12,6)
- default_enabled
- is_active
- sort_order
- timestamps

Examples:
- Breakfast = 0.5
- Lunch = 1.0
- Dinner = 1.0
- Special Meal = 1.5

Meal factors are configurable.

### meal_schedules

- id
- mess_id
- meal_type_id
- schedule_date
- is_available
- start_time
- end_time
- created_by
- timestamps

---

# 6. Meal Management Tables

### meal_entries

- id
- tenant_id
- mess_id
- period_id
- member_id
- meal_date
- tracking_mode (individual, daily_total)
- total_units DECIMAL(12,4)
- status (draft, submitted, approved, rejected, voided)
- notes
- created_by
- approved_by
- approved_at
- timestamps

Unique: period_id + member_id + meal_date.

This unique constraint prevents two active daily entries for the same member and date.

The application must prevent simultaneous individual and daily-total tracking for one member on the same date.

### meal_entry_items

- id
- meal_entry_id
- meal_type_id
- quantity DECIMAL(12,4)
- unit_factor_snapshot DECIMAL(12,6)
- weighted_units DECIMAL(12,4)
- timestamps

Unique: meal_entry_id + meal_type_id.

For individual tracking:

weighted_units = quantity × unit_factor_snapshot.

The factor must be snapshotted when an entry is submitted or approved, so later configuration changes cannot silently alter historical records.

### meal_adjustments

- id
- tenant_id
- mess_id
- period_id
- member_id
- meal_entry_id (nullable)
- adjustment_units
- reason
- status
- created_by
- approved_by
- timestamps

### guest_meals

- id
- tenant_id
- mess_id
- period_id
- guest_name
- sponsor_member_id (nullable)
- meal_type_id (nullable)
- meal_date
- quantity
- unit_factor_snapshot
- weighted_units
- charge_amount
- payment_status
- notes
- created_by
- timestamps

Guest meals must be included in the food consumption denominator when the configured policy requires it. Guest charges must be separately tracked to avoid double counting.

---

# 7. Bazar Management Tables

### bazar_entries

- id
- tenant_id
- mess_id
- period_id
- purchased_by
- purchase_date
- entry_mode (total, itemized)
- description
- subtotal
- discount
- total_amount
- payment_method
- payment_status
- status (draft, submitted, approved, rejected, voided)
- receipt_path
- created_by
- approved_by
- approved_at
- timestamps

### bazar_items

- id
- bazar_entry_id
- item_name
- category
- quantity DECIMAL(12,4)
- unit
- unit_price DECIMAL(15,4)
- discount
- total
- timestamps

For total mode, only the overall amount is required.

For itemized mode, the total must be calculated from approved item details.

The system must prevent manually supplied totals from conflicting with item totals.

### bazar_categories

- id
- mess_id
- name
- is_food_related
- is_active
- timestamps

Examples:
- Rice
- Fish
- Meat
- Vegetables
- Grocery
- Cleaning Supplies

A bazar category marked as food-related contributes to the food expense pool.

---

# 8. Expense Management Tables

### expense_categories

- id
- tenant_id
- mess_id
- name
- category_type (food, utility, rent, maintenance, salary, other)
- is_active
- timestamps

### expenses

- id
- tenant_id
- mess_id
- period_id
- category_id
- title
- description
- amount
- expense_date
- allocation_method (equal, meal_based, custom, usage_based, excluded)
- expense_source (bazar, manual, recurring)
- status (draft, submitted, approved, rejected, voided)
- paid_by_member_id (nullable)
- payment_method
- receipt_path
- created_by
- approved_by
- approved_at
- timestamps

### expense_allocations

- id
- expense_id
- member_id
- allocated_amount
- allocation_basis
- timestamps

Allocation amounts must sum to the approved expense amount, except for explicitly excluded expenses.

### expense_attachments

- id
- expense_id
- file_path
- original_name
- mime_type
- file_size
- uploaded_by
- timestamps

Important rule: bazar purchases and manual food expenses must not be counted twice. The calculation engine must use a single authoritative expense source for each transaction.

---

# 9. Financial Management Tables

### financial_accounts

- id
- tenant_id
- mess_id
- name
- account_type (cash, bank, mobile_wallet, receivable, payable, expense, income, equity)
- currency
- opening_balance
- is_active
- timestamps

### ledger_transactions

- id
- tenant_id
- mess_id
- transaction_number
- transaction_date
- description
- reference_type
- reference_id
- status (draft, posted, reversed)
- created_by
- posted_at
- timestamps

### ledger_lines

- id
- transaction_id
- account_id
- member_id (nullable)
- debit DECIMAL(15,2)
- credit DECIMAL(15,2)
- description
- timestamps

Every posted transaction must have total debits equal to total credits.

### deposits

- id
- tenant_id
- mess_id
- period_id
- member_id
- amount
- payment_method
- financial_account_id
- transaction_reference
- paid_at
- status (pending, approved, rejected, reversed)
- received_by
- approved_by
- ledger_transaction_id
- notes
- timestamps

### refunds

- id
- tenant_id
- mess_id
- period_id
- member_id
- amount
- payment_method
- reason
- status
- ledger_transaction_id
- processed_by
- processed_at
- timestamps

### financial_adjustments

- id
- tenant_id
- mess_id
- period_id
- member_id
- adjustment_type (credit, debit)
- amount
- reason
- status
- created_by
- approved_by
- ledger_transaction_id
- timestamps

Financial records must not be hard-deleted after posting. Corrections require reversal transactions.

---

# 10. Settlement Tables

### monthly_snapshots

- id
- tenant_id
- mess_id
- period_id
- total_food_expense
- total_weighted_meals
- calculated_meal_rate
- total_other_expenses
- total_deposits
- total_adjustments
- calculation_version
- calculation_details (JSON)
- generated_at
- timestamps

### settlements

- id
- tenant_id
- mess_id
- period_id
- member_id
- opening_credit
- meal_units
- meal_cost
- other_expense
- other_credits
- other_debits
- total_deposits
- total_payable
- closing_credit
- status (draft, reviewed, finalized, paid, partially_paid)
- finalized_at
- timestamps

### settlement_items

- id
- settlement_id
- item_type
- description
- quantity
- unit_rate
- amount
- calculation_reference (JSON)
- timestamps

Settlement must be reproducible using the saved calculation snapshot.

### settlement_payments

- id
- settlement_id
- amount
- payment_type (collection, refund)
- payment_method
- transaction_reference
- ledger_transaction_id
- paid_at
- received_by
- timestamps

---

# 11. Supporting Tables

### audit_logs
- id
- tenant_id
- mess_id (nullable)
- user_id
- action
- auditable_type
- auditable_id
- old_values (JSON)
- new_values (JSON)
- ip_address
- user_agent
- created_at

### notifications
- id
- tenant_id
- user_id
- type
- title
- message
- data (JSON)
- read_at
- timestamps

### mess_announcements
- id
- tenant_id
- mess_id
- title
- body
- published_at
- expires_at
- created_by
- timestamps

### system_settings
- id
- tenant_id
- mess_id (nullable)
- key
- value (JSON)
- timestamps

### idempotency_keys
- id
- tenant_id
- idempotency_key
- request_hash
- response_data (JSON)
- expires_at
- timestamps

Unique: tenant_id + idempotency_key.

---

# PART 3 — DATABASE RELATIONSHIPS

## 12. Eloquent Relationship Map

### Tenant
- belongsTo User as owner
- belongsToMany User through tenant_users
- hasMany Mess
- hasMany Subscription

### Mess
- belongsTo Tenant
- hasMany MessMember
- hasMany AccountingPeriod
- hasMany MealType
- hasMany MealEntry
- hasMany BazarEntry
- hasMany Expense
- hasMany Deposit
- hasMany Settlement

### MessMember
- belongsTo Mess
- belongsTo User (nullable)
- hasMany MealEntry
- hasMany Deposit
- hasMany Settlement
- belongsToMany Role

### AccountingPeriod
- belongsTo Mess
- hasMany MealEntry
- hasMany BazarEntry
- hasMany Expense
- hasMany Deposit
- hasMany Settlement

### MealEntry
- belongsTo Mess
- belongsTo AccountingPeriod
- belongsTo MessMember
- hasMany MealEntryItem
- belongsTo User as creator

### MealEntryItem
- belongsTo MealEntry
- belongsTo MealType

### BazarEntry
- belongsTo Mess
- belongsTo AccountingPeriod
- belongsTo MessMember as purchaser
- hasMany BazarItem

### Expense
- belongsTo Mess
- belongsTo AccountingPeriod
- belongsTo ExpenseCategory
- hasMany ExpenseAllocation

### Deposit
- belongsTo Mess
- belongsTo MessMember
- belongsTo AccountingPeriod
- belongsTo LedgerTransaction

### Settlement
- belongsTo Mess
- belongsTo AccountingPeriod
- belongsTo MessMember
- hasMany SettlementItem
- hasMany SettlementPayment

---

# PART 4 — MIGRATION ORDER

## 13. Recommended Migration Sequence

Create migrations in this order to avoid foreign-key dependency issues.

1. users
2. tenants
3. tenant_users
4. subscription_plans
5. subscriptions
6. subscription_payments
7. invoices
8. platform_settings
9. roles
10. permissions
11. role_permissions
12. messes
13. mess_members
14. mess_member_roles
15. mess_invitations
16. accounting_periods
17. meal_types
18. meal_schedules
19. meal_entries
20. meal_entry_items
21. meal_adjustments
22. guest_meals
23. bazar_categories
24. bazar_entries
25. bazar_items
26. expense_categories
27. expenses
28. expense_allocations
29. expense_attachments
30. financial_accounts
31. ledger_transactions
32. ledger_lines
33. deposits
34. refunds
35. financial_adjustments
36. monthly_snapshots
37. settlements
38. settlement_items
39. settlement_payments
40. audit_logs
41. notifications
42. mess_announcements
43. system_settings
44. idempotency_keys

Add missing foreign keys after referenced tables exist.

Use restrict-on-delete for financial and historical records.

Use cascade only for safe dependent records that cannot exist independently, such as unposted draft items.

Do not cascade-delete posted financial records.

## 14. Important Database Constraints

- Unique tenant slug.
- Unique tenant-user pair.
- Unique tenant + mess slug.
- Unique mess + member code.
- Unique accounting period + member + meal date.
- Unique meal entry + meal type.
- Unique tenant + idempotency key.
- Unique invoice number within tenant.
- Foreign key consistency between tenant, mess and child resources.
- Nonnegative financial amounts where negative values are not permitted.
- Check constraints for debit/credit values.
- Check constraint for accounting period date ranges.
- No overlapping accounting periods per mess.

MySQL does not provide a simple exclusion constraint for overlapping date ranges. Enforce overlap validation in a transaction with appropriate locking, and add concurrency tests.

---

# PART 5 — CALCULATION BUSINESS RULES

## 15. Meal Tracking Modes

Support two tracking modes.

### Mode A: Individual

Example:

Breakfast = 1
Lunch = 1
Dinner = 1

Weighted units:

(1 × 0.5) + (1 × 1.0) + (1 × 1.0) = 2.5

### Mode B: Daily Total

Example:

Total units = 2.5

Daily total is stored directly as weighted units by default.

Optionally allow an administrator to enter raw meal count and apply a configured factor, but the saved calculation must record exactly how the total was derived.

One member cannot use both modes on the same date.

## 16. Meal Rate Strategies

Implement a strategy interface.

MealRateStrategy:

- calculate()
- validate()
- getBreakdown()

Initial strategies:

1. WeightedMealRateStrategy
2. EqualMealRateStrategy
3. ConfigurableCustomStrategy

Default strategy:

Meal Rate = Eligible Food Expense / Total Eligible Weighted Units.

The selected strategy and its configuration must be saved in the monthly snapshot.

## 17. Food Expense Pool

Food expenses include approved food-related bazar entries and approved manual food expenses.

Do not count the same bazar entry again as a manual expense.

Formula:

Food Expense Pool =
Approved Food Bazar Purchases
+ Approved Manual Food Expenses
+ Approved Food Adjustments

Exclude:
- Rejected transactions
- Draft transactions
- Voided transactions
- Non-food expenses
- Transactions outside the accounting period

## 18. Meal Denominator

Total Eligible Weighted Units =

Member Approved Meal Units
+ Eligible Guest Meal Units
+ Approved Meal Adjustments

The system must use the same eligibility policy for both numerator and denominator.

If guest meals are excluded from the denominator, their food cost must be allocated separately. Do not silently include their cost in the member meal rate.

## 19. Meal Rate Formula

Weighted Meal Rate:

Food Expense Pool / Total Eligible Weighted Units.

Example:

Food Expense = 15,000
Weighted Units = 200

Rate = 75.

If weighted units are zero, return a calculation exception or a zero-unit state. Never divide by zero.

## 20. Member Meal Cost

Member Meal Cost = Member Eligible Weighted Units × Final Meal Rate.

Use decimal-safe arithmetic and configured precision.

## 21. Other Expense Allocation

Supported methods:

### Equal
Approved expense / eligible members.

### Meal Based
Approved expense × member meal units / total eligible member meal units.

### Custom
Use saved member allocations. Validate that allocated amounts equal the expense total.

### Usage Based
Use explicit recorded usage quantities and their allocation basis.

### Excluded
Do not allocate to members.

A member eligibility policy must define whether inactive members and members who left during the period are included.

Use effective membership dates, not current membership status alone.

## 22. Member Settlement Formula

Define opening_credit as the amount already available to the member at the beginning of the period.

Positive closing_credit means the mess owes the member.

Negative closing_credit means the member owes the mess.

Closing Credit =

Opening Credit
+ Approved Deposits
+ Other Member Credits
- Meal Cost
- Other Expense Share
- Other Member Debits.

Example:

Opening Credit = 0
Deposits = 5,000
Meal Cost = 3,375
Other Expense = 400

Closing Credit = 1,225.

The member has 1,225 available for refund or future carry-forward.

If the result is -1,225, the member owes 1,225.

## 23. Accounting Ledger Rules

Implement double-entry accounting.

Every posted transaction must satisfy:

Total Debit = Total Credit.

Examples:

Member deposits:
- Debit: Cash/Bank
- Credit: Member Liability / Member Credit

Food purchase:
- Debit: Food Expense
- Credit: Cash/Bank or Payable

Settlement charge:
- Debit: Member Receivable
- Credit: Expense Recovery / Settlement Account

Refund:
- Debit: Member Liability
- Credit: Cash/Bank

Use the correct account mappings and avoid treating the same financial transaction as two separate expenses.

Reversals must reference the original transaction.

## 24. Settlement Finalization

Finalization steps:

1. Lock the accounting period for ordinary edits.
2. Validate all meal entries.
3. Validate bazar and expenses.
4. Validate deposits and ledger.
5. Calculate food expense pool.
6. Calculate weighted meal units.
7. Calculate meal rate.
8. Calculate member expense allocations.
9. Calculate member closing balances.
10. Reconcile ledger and settlements.
11. Save immutable calculation snapshot.
12. Save settlement details.
13. Mark period finalized.
14. Generate member statements.

Reopening must require:
- Special permission
- Reason
- Audit event
- Recalculation/version tracking
- Explicit settlement correction policy

Never silently overwrite a finalized financial snapshot.

---

# PART 6 — CLAUDE CODE DEVELOPMENT PROMPTS

## 25. Global Instructions for Claude

Use the following instructions throughout the project.

You are a senior Laravel SaaS architect and full-stack developer.

Build MessMate, a production-quality multi-tenant mess management SaaS application.

### Mandatory Technology

- Laravel
- PHP 8.3+
- MySQL 8
- Blade
- Bootstrap 5
- Vanilla JavaScript or Alpine.js
- Chart.js
- Laravel Queue
- PHPUnit or Pest

### Mandatory Development Rules

1. Use modular Laravel architecture.
2. Keep controllers thin.
3. Put business logic in services.
4. Use Form Requests for validation.
5. Use Policies for authorization.
6. Use database transactions for financial operations.
7. Use decimal-safe monetary calculations.
8. Never trust tenant_id or mess_id supplied by the frontend.
9. Enforce tenant and mess ownership on every relevant query.
10. Use eager loading to avoid N+1 queries.
11. Add database indexes.
12. Use meaningful naming conventions.
13. Write automated tests for critical logic.
14. Never delete posted financial transactions.
15. Maintain backward compatibility where practical.
16. Do not create fake functionality or placeholder buttons.
17. Do not duplicate business logic across controllers.
18. Do not skip migrations, seeders or tests.
19. Keep the application usable on mobile.
20. Follow the project's existing conventions if they are already established and sound.

### Claude Working Rules

- Inspect the existing project before changing files.
- Do not overwrite existing code without understanding its purpose.
- Maintain a development checklist.
- Implement one phase at a time.
- Run migrations and tests after each phase.
- Report files created or modified.
- Report commands executed and test results.
- Explain unresolved issues honestly.
- Do not move to another phase until the current phase passes its acceptance criteria.
- Do not fabricate successful test results.
- Never generate an entire large application in one response.

---

# PROMPT 1 — PROJECT FOUNDATION & SAAS ARCHITECTURE

Build the foundational architecture of MessMate SaaS.

### Tasks

1. Inspect the Laravel project and existing dependencies.
2. Configure application environment and database.
3. Establish a clean modular folder structure.
4. Configure authentication.
5. Implement users and profiles.
6. Implement tenant creation.
7. Implement tenant-user membership.
8. Implement tenant context resolution.
9. Implement tenant middleware.
10. Implement tenant-aware authorization.
11. Implement mess creation and management.
12. Implement the user-to-multiple-messes relationship.
13. Implement tenant and mess switching.
14. Create initial platform and tenant dashboards.
15. Implement role and permission infrastructure.
16. Create database factories and seeders.

### Required Architecture

Platform context:
- Super Admin
- Platform dashboard
- Tenant administration

Tenant context:
- Organization owner
- Tenant users
- Tenant settings

Mess context:
- Mess administrator
- Manager
- Accountant
- Bazar Manager
- Member

### Security Requirements

- Tenant data must never leak across organizations.
- Mess data must never leak across messes.
- Every route must verify membership and permissions.
- Prevent insecure direct object reference attacks.
- Validate tenant and mess relationships on the server.
- Use tenant-scoped cache keys.
- Test queue tenant context.

### Deliverables

- Complete migrations
- Eloquent models
- Relationships
- Middleware
- Policies
- Authentication
- Role management
- Dashboards
- Seeders
- Automated tests
- Setup documentation

### Acceptance Criteria

- A tenant can create multiple messes.
- A user can join multiple messes.
- A user can have different roles in different messes.
- Cross-tenant data access is rejected.
- Cross-mess data access is rejected.
- Authentication works.
- All migrations execute successfully.

Do not begin meal or finance modules in this phase.

---

# PROMPT 2 — MEMBER, ACCOUNTING PERIOD & MEAL MANAGEMENT

Implement the complete member and meal management modules.

### Member Management

- Create and edit members.
- Invite members.
- Link members to registered users.
- Support members without login accounts.
- Track joining and leaving dates.
- Support inactive and suspended members.
- Assign mess-specific roles.
- Display member statements.

### Accounting Period

Implement both:

1. Calendar month periods.
2. Custom start and end date periods.

Requirements:
- Create periods.
- Validate date ranges.
- Prevent overlapping periods.
- Prevent duplicate active periods.
- Open, review, finalize and lock periods.
- Support authorized reopening.

### Meal Configuration

Create configurable meal types.

Each meal type must have:
- Name
- Code
- Unit factor
- Active status
- Default availability
- Sort order

### Meal Entry

Implement individual and daily-total tracking.

Individual:
- Breakfast
- Lunch
- Dinner
- Other configurable meal types

Daily total:
- Enter weighted total units.
- Support optional factor-based conversion.

Features:
- Bulk entry
- Daily entry
- Calendar view
- Member-specific history
- Guest meals
- Meal adjustments
- Meal approval
- Rejection
- Entry correction
- Audit logs

### Critical Rules

- One active daily entry per member per date and period.
- Never combine individual and daily-total modes for the same day.
- Save factor snapshots.
- Do not alter historical meal calculations when meal configuration changes.
- Validate effective membership dates.
- Lock edits for finalized periods.

### UI Requirements

- Responsive meal entry table.
- Monthly calendar.
- Bulk member selection.
- Daily summary.
- Member meal history.
- Pending approval screen.

### Tests

- Individual tracking.
- Daily total tracking.
- Duplicate prevention.
- Mode conflict prevention.
- Meal factor calculations.
- Guest meal calculation.
- Mid-month joining.
- Mid-month leaving.
- Locked period restrictions.

Do not implement final meal rate calculation in this phase.

---

# PROMPT 3 — BAZAR, EXPENSE & FINANCIAL MANAGEMENT

Implement complete bazar and financial modules.

### Bazar Management

Support both:

1. Total amount entry.
2. Itemized grocery entry.

Total mode:
- Purchase date
- Purchaser
- Description
- Total amount
- Receipt
- Payment method

Itemized mode:
- Item name
- Category
- Quantity
- Unit
- Unit price
- Discount
- Line total

Features:
- Add, edit and view bazar entries.
- Upload receipts.
- Approval workflow.
- Rejection.
- Void and correction handling.
- Bazar category management.
- Food-related category configuration.

### Expense Management

Implement:
- Rent
- Electricity
- Gas
- Water
- Internet
- Cleaning
- Salary
- Maintenance
- Miscellaneous
- Food-related manual expenses

Support:
- Equal allocation
- Meal-based allocation
- Custom allocation
- Usage-based allocation
- Excluded expenses

### Deposits

Implement:
- Member deposit
- Cash
- Bank
- Mobile wallet
- Payment reference
- Receipt
- Approval
- Reversal
- Deposit history

### Double-Entry Ledger

Implement:
- Financial accounts
- Ledger transactions
- Ledger lines
- Debit and credit validation
- Transaction posting
- Transaction reversal
- Account balances

### Critical Requirements

- Debit must equal credit.
- Posted transactions cannot be edited or deleted.
- Corrections must use reversal entries.
- Use DB transactions.
- Prevent duplicate payment posting.
- Implement idempotency.
- Never trust amounts submitted from the browser.
- Prevent duplicate bazar/expense inclusion.
- All financial transactions must be tenant and mess scoped.

### Deliverables

- Models
- Migrations
- Services
- Controllers
- Form Requests
- Policies
- Views
- Reports
- Factories
- Tests

Do not implement monthly settlement yet.

---

# PROMPT 4 — CALCULATION ENGINE & MONTHLY SETTLEMENT

This is the most important business logic module.

Implement the calculation engine independently from controllers.

### Calculation Architecture

Create:

- MealCalculationService
- MealRateCalculator
- MealRateStrategyInterface
- WeightedMealRateStrategy
- EqualMealRateStrategy
- ConfigurableCustomStrategy
- FoodExpensePoolService
- ExpenseAllocationService
- MemberBalanceService
- SettlementService
- FinancialReconciliationService

### Meal Rate

Calculate the food expense pool using approved food-related purchases and expenses.

Exclude:
- Draft entries
- Rejected entries
- Voided entries
- Non-food expenses
- Out-of-period transactions

Prevent duplicate inclusion.

### Weighted Meal Units

Calculate:
- Approved member meal units
- Eligible guest units
- Approved adjustments

Use historical factor snapshots.

### Meal Rate Formula

Meal rate = Eligible Food Expense / Eligible Weighted Units.

Handle zero-unit periods safely.

### Member Calculation

For each eligible member:

1. Calculate meal units.
2. Calculate meal cost.
3. Calculate other expense allocation.
4. Calculate approved deposits.
5. Calculate opening credit.
6. Calculate adjustments.
7. Calculate final closing credit.

### Balance Convention

Positive closing credit = Mess owes member.

Negative closing credit = Member owes mess.

Formula:

Closing Credit =
Opening Credit
+ Deposits
+ Other Credits
- Meal Cost
- Other Expenses
- Other Debits.

### Monthly Settlement

Implement:
- Draft settlement generation
- Review
- Finalization
- Locking
- Member statement
- Settlement payments
- Refunds
- Carry-forward balances

### Financial Integrity

- Use decimal-safe calculations.
- Use database transactions.
- Use row locking where required.
- Prevent duplicate settlement.
- Save calculation version.
- Save complete calculation breakdown.
- Reconcile all totals.
- Never overwrite a finalized snapshot silently.
- Support controlled reopening with audit logs.

### Critical Test Scenarios

Create automated tests for:
- Zero meals.
- Zero food expenses.
- Different meal factors.
- Individual and daily-total modes.
- Guest meals.
- Custom expense allocation.
- Equal allocation.
- Meal-based allocation.
- Member joining mid-period.
- Member leaving mid-period.
- Multiple deposits.
- Refunds.
- Opening credits.
- Negative closing balance.
- Positive closing balance.
- Decimal rounding.
- Reversal transactions.
- Duplicate settlement generation.
- Reopening finalized periods.

Do not proceed until calculation tests pass.

---

# PROMPT 5 — DASHBOARDS, REPORTS & MEMBER PORTAL

Build the complete frontend experience.

### Design Direction

Use a polished modern SaaS interface.

- Bootstrap 5
- Blade components
- Responsive sidebar
- Top navigation
- Clean typography
- Light dashboard theme
- Consistent spacing
- Reusable forms
- Data tables
- Modal dialogs
- Toast notifications
- Empty states
- Loading states
- Validation feedback

Do not use oversized decorative elements.

### Admin Dashboard

Display:
- Active members
- Total meals
- Estimated meal rate
- Total bazar expense
- Total other expense
- Deposits
- Outstanding balance
- Pending approvals
- Recent transactions
- Daily meal trends
- Expense trends

### Member Portal

Members can view:
- Today's meal
- Meal calendar
- Monthly total units
- Estimated meal rate
- Personal meal cost
- Other expenses
- Deposits
- Current balance
- Transaction history
- Monthly statements
- Announcements

If self-service meal entry is enabled:
- Members can submit their own meal entries.
- Admin can configure approval requirements.
- Members cannot edit approved entries without authorization.

### Reports

Implement:
- Daily meal report
- Monthly meal report
- Member meal report
- Bazar report
- Expense report
- Deposit report
- Member ledger
- Settlement report
- Outstanding report
- Financial summary

### Export

- Excel
- PDF
- Print-friendly reports

### Performance

- Pagination
- Server-side filtering
- Eager loading
- Indexed search
- Efficient aggregate queries
- Cache suitable dashboard summaries

### Acceptance Criteria

All reports must use actual database values and respect tenant/mess permissions.

No static demo numbers in production views.

---

# PROMPT 6 — SAAS SUBSCRIPTION & PLATFORM ADMINISTRATION

Build the commercial SaaS layer.

### Platform Administration

Implement:
- Platform dashboard
- Tenant listing
- Tenant details
- Tenant suspension
- Tenant activation
- Subscription management
- Plan management
- Invoice management
- Payment records
- Platform settings
- Support tickets
- Announcements

### Subscription Plans

Support:
- Free
- Basic
- Pro
- Enterprise

Plan configuration:
- Price
- Currency
- Monthly/yearly billing
- Maximum messes
- Maximum members per mess
- Maximum users
- Feature availability
- Usage limits

### Subscription Features

- Trial
- Upgrade
- Downgrade
- Renewal
- Expiration
- Grace period
- Cancellation
- Manual payment
- Online gateway integration
- Invoice generation
- Payment history

### Payment Gateway Architecture

Create a provider interface.

Support configurable providers without hard-coding payment logic into controllers.

Payment workflow:
1. Create payment intent.
2. Redirect or initialize provider.
3. Verify callback signature.
4. Verify payment with provider where applicable.
5. Process idempotently.
6. Update subscription.
7. Generate invoice.
8. Send notification.

Never trust frontend payment success.

### Subscription Enforcement

- Enforce plan limits on the server.
- Never delete financial or mess data after expiration.
- Provide read-only access during applicable grace/expired states.
- Allow authorized data export.
- Keep subscription events in history.

### Acceptance Criteria

- Plan restrictions work.
- Subscription renewals work.
- Duplicate callbacks do not create duplicate payments.
- Failed payments do not activate plans.
- Expired subscriptions follow configured access policy.

---

# PROMPT 7 — SECURITY, PERFORMANCE & PRODUCTION RELEASE

Perform a complete production-readiness audit.

### Security Audit

- Authentication
- Authorization
- Tenant isolation
- Mess isolation
- IDOR protection
- CSRF
- XSS
- SQL injection prevention
- File upload validation
- Private file access
- Rate limiting
- Session security
- Password reset
- Financial integrity
- Payment callback verification

### Performance Audit

- N+1 query detection
- Database indexing
- Slow query review
- Dashboard optimization
- Pagination
- Queue processing
- Cache isolation
- Large report optimization
- Memory usage

### Automated Testing

Create:
- Unit tests
- Feature tests
- Integration tests
- Calculation tests
- Authorization tests
- Tenant isolation tests
- Subscription tests
- Ledger reconciliation tests

### Deployment

Prepare:
- Production environment example
- Database migration guide
- Seeder guide
- Storage configuration
- Queue configuration
- Scheduler configuration
- Backup instructions
- Error logging
- Installation documentation

### Commercial Deliverables

Prepare:
- Installation wizard
- Environment validation
- Database configuration
- Admin creation
- Demo data option
- README
- User documentation
- Developer documentation
- Changelog
- License documentation
- Upgrade instructions
- Backup and restore guide

### Final Acceptance Criteria

The application must:
- Install successfully on a fresh environment.
- Run all migrations.
- Pass automated tests.
- Prevent cross-tenant data access.
- Reconcile financial records.
- Generate correct monthly settlements.
- Support subscription restrictions.
- Work on desktop and mobile.
- Have no fake buttons or unfinished critical workflows.

Provide a final audit report with any known limitations.

---

# PART 7 — FINAL DEVELOPMENT WORKFLOW

## 26. How to Use These Prompts

Use Claude Code in the project root.

### Step 1
Start with Prompt 1.

Ask Claude to inspect the project before generating code.

### Step 2
Review the generated architecture and migrations.

Run the application and test authentication and tenant isolation.

### Step 3
Give Prompt 2.

Test both meal tracking methods independently.

### Step 4
Give Prompt 3.

Test deposits, bazar, expenses and ledger balancing.

### Step 5
Give Prompt 4.

This is the most important phase. Review all calculation tests before proceeding.

### Step 6
Give Prompt 5.

Review the admin and member experiences on desktop and mobile.

### Step 7
Give Prompt 6.

Test subscription restrictions and payment workflows.

### Step 8
Give Prompt 7.

Run a complete security and production audit.

## 27. Final Development Rules

- Do not combine all phases into one massive code-generation request.
- Always review migrations before migrating production data.
- Never trust financial calculations from the frontend.
- Do not let Claude silently alter database architecture between phases.
- Maintain a PROJECT_STATUS.md file.
- Maintain a DATABASE_SCHEMA.md file.
- Maintain a BUSINESS_RULES.md file.
- Maintain a CHANGELOG.md file.
- Run tests after every major change.
- Require approval before destructive migrations.
- Preserve all finalized accounting records.

**Final objective:** Deliver a maintainable, secure, multi-tenant Laravel SaaS product that can be operated by real mess organizations and commercialized as a subscription-based software product.
