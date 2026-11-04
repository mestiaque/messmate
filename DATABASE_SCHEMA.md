# Database Schema

Implemented tables (Phase 1). Monetary/meal tables are added in later phases per `masterprompt.md` §2–11.

| Table | Key points |
|---|---|
| users | email and phone both nullable + unique; `status`; `is_platform_admin` |
| tenants | `owner_id` → users (restrict); unique `slug`; soft deletes |
| tenant_users | unique (tenant_id, user_id) |
| platform_settings | unique `key`, JSON value |
| roles | `tenant_id` NULL = system role; `scope` platform/tenant/mess; unique (tenant_id, slug, scope) |
| permissions | unique `name`; `module` |
| role_permissions | PK (role_id, permission_id) |
| messes | unique (tenant_id, slug); unique (id, tenant_id) as composite-FK target; soft deletes |
| mess_members | FK (mess_id, tenant_id) → messes(id, tenant_id) so tenant_id cannot disagree with the mess; unique (mess_id, member_code); generated `active_user_key` + unique (mess_id, active_user_key) = one active membership per user per mess; unique (id, mess_id) as FK target |
| mess_member_roles | FK (member_id, mess_id) → mess_members(id, mess_id); unique (member_id, role_id) |
| mess_invitations | token stored only as SHA-256 hash; FK (mess_id, tenant_id) → messes |
| audit_logs | append-only (no `updated_at`) |

| accounting_periods | CHECK end_date >= start_date; unique (id, mess_id) as FK target; overlap enforced in service under lock |
| meal_types | unique (mess_id, code); `unit_factor` DECIMAL(12,6) CHECK >= 0 |
| meal_entries | unique (period_id, member_id, meal_date); composite FKs pin period and member to the same mess; `raw_count` + `conversion_factor` (additions to spec) record daily-total derivation; `rejection_reason` (addition) |
| meal_entry_items | unique (meal_entry_id, meal_type_id); `unit_factor_snapshot`; cascade only from its entry |
| meal_adjustments | signed `adjustment_units`; composite FKs to period/member in same mess |
| guest_meals | composite FKs keep sponsor and meal type in the same mess; `status` + approval columns (additions to spec) |
| mess_invitations.member_id | (addition) membership created on acceptance |

| financial_accounts | `system_code` (addition) unique per mess for accounts the system posts to |
| ledger_transactions | unique (mess_id, transaction_number); `reverses_transaction_id` (addition, unique) links a reversal to its original |
| ledger_lines | `mess_id` (addition) so composite FKs pin transaction, account and member to one mess; CHECK one positive side |
| bazar_entries | `category_id` (total mode), `food_amount` (server-computed food share), `rejection_reason`, `ledger_transaction_id` (additions); CHECK total = subtotal − discount, 0 ≤ food ≤ total |
| bazar_items | `category_id` FK instead of free-text category |
| bazar_categories / expense_categories | unique name per mess; deactivated, never deleted |
| expenses | `rejection_reason`, `ledger_transaction_id` (additions); CHECK amount > 0 |
| expense_allocations | `mess_id` (addition) for same-mess FKs; `allocation_basis` holds usage quantity |
| deposits | unique (mess_id, payment_method, transaction_reference); `receipt_path`, `rejection_reason` (additions) |
| idempotency_keys | unique (tenant_id, idempotency_key), 24 h expiry |

| system_settings | unique (tenant_id, mess_id, key); holds `settlement_policy` |
| financial_adjustments | `effective_date`, `ledger_transaction_id` (additions); CHECK amount > 0 |
| monthly_snapshots | `calculation_version` unique per period, `status` draft/final/superseded, `strategy`, `ledger_transaction_id`, `superseded_*` (additions); final rows immutable at model level |
| settlements | `snapshot_id` (addition) — unique (snapshot_id, member_id) instead of (period, member) so versions coexist; `guest_cost`, `paid_amount` (additions) |
| settlement_items | signed `amount` (+ = in the member's favour) |
| settlement_payments | `mess_id` (addition); CHECK amount > 0 |

| subscription_plans | unique slug; CHECK prices ≥ 0; NULL limits = unlimited |
| subscriptions | unique tenant_id (one live row; history in `subscription_events`) |
| invoices | unique (tenant_id, invoice_number); `plan_id`, `billing_cycle`, `description` (additions); CHECK total = subtotal − discount + tax |
| subscription_payments | unique `provider_reference`; `invoice_id`, `created_by`, `confirmed_by` (additions) |
| subscription_events | (addition) append-only history |
| support_tickets / support_ticket_messages | (addition) tenant ↔ platform support |
| platform_announcements | (addition) shown to every organization |

`mess_members.user_id` FK is RESTRICT, not SET NULL: MySQL does not allow SET NULL on a base column of the stored
generated column `active_user_key`.

Plus Laravel defaults: password_reset_tokens, sessions, cache, jobs, job_batches, failed_jobs.

FK delete rules: restrict for tenant/mess/history; cascade only for pure join rows.
