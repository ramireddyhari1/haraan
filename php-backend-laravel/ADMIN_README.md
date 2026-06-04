Admin & Partner Dashboard Guide

This project has two internal portals:

- Admin dashboard for platform ownership, moderation, event operations, GameHub operations, finance, users, and access control.
- Partner dashboard for partner-scoped management of their own events, GameHub content, and workers.

Both portals use Laravel session auth plus role checks. The ERP gate is only the entry point; once a user is logged in, the routes stay accessible without repeatedly supplying the ERP key.

## 1. How the portal works

The flow is:

1. Open the ERP entry URL in development.
2. Use the ERP key once to reach the internal login pages.
3. Log in as an admin or partner.
4. The `auth` middleware keeps the session alive.
5. The `EnsureRole` middleware limits each route group to the right role.
6. Views load Blade pages, and the pages fetch JSON endpoints for lists and CRUD actions.

### Access layers

- ERP discovery gate: `erp.key`
- Authentication: Laravel session login
- Role enforcement: `EnsureRole`
- CSRF protection: used on POST, PUT, and DELETE requests

## 2. Local setup

Start the app:

```bash
php artisan serve
```

Default dev database:

- SQLite at `database/database.sqlite`

Default ERP key in development:

- `letmein`

ERP entry page:

- `http://127.0.0.1:8000/erp?key=letmein`

## 3. First admin login

There are two ways to create the first admin account.

### Option A: Seeder

```bash
php artisan db:seed --class=AdminUserSeeder
```

Default seeded credentials:

- Email: `admin@local`
- Password: `password`

### Option B: One-time setup route

```text
GET /erp/setup-admin?key=letmein&email=admin@local&password=password
```

Use this only for local development.

## 4. Login flow

### Admin

- Login page: `GET /admin/login`
- Login submit: `POST /admin/login`
- Logout: `POST /admin/logout`

### Partner

- Login page: `GET /partner/login`
- Login submit: `POST /partner/login`
- Logout: `POST /partner/logout`

Password reset routes also exist for both roles:

- Admin reset request: `GET /admin/password/reset`
- Partner reset request: `GET /partner/password/reset`

## 5. Admin dashboard structure

The admin dashboard is organized into functional sections instead of a single long list.

### Sidebar sections

- Core
- Events & GameHub
- Operations
- Financials
- System

### Main admin landing page

- Route: `GET /admin`
- Name: `admin.dashboard`
- Purpose: summary cards and module shortcuts

## 6. Admin features

### Events

Purpose:

- Manage all event types such as singing, dance, quiz, cultural events, sports, and any custom event track.

Routes:

- View page: `GET /admin/events`
- List JSON: `GET /admin/events/json`
- New form: `GET /admin/events/new`
- Create: `POST /admin/events`
- Edit form: `GET /admin/events/{id}/edit`
- Update: `PUT /admin/events/{id}`
- Delete: `DELETE /admin/events/{id}`

### GameHub

Purpose:

- Handle game/tournament content separately from standard events.
- Keep live scoring, fixtures, leaderboards, and match control in one lane.

Routes:

- View page: `GET /admin/gamehub`

### Partners

Purpose:

- Approve and manage partner accounts.
- Assign or review who owns which event or GameHub program.

Routes:

- View page: `GET /admin/partners`
- List JSON: `GET /admin/partners/json`
- New form: `GET /admin/partners/new`
- Create: `POST /admin/partners`
- Edit form: `GET /admin/partners/{id}/edit`
- Update: `PUT /admin/partners/{id}`
- Delete: `DELETE /admin/partners/{id}`

### Team

Purpose:

- Manage co-admins and workers separately.
- Co-admins handle restricted admin work.
- Workers handle operational staff tasks.

Routes:

- Co-admins page: `GET /admin/co-admins`
- Workers page: `GET /admin/workers`
- Team JSON: `GET /admin/team/{role}/json`
- Team new form: `GET /admin/team/{role}/new`
- Team create: `POST /admin/team/{role}`
- Team edit form: `GET /admin/team/{role}/{id}/edit`
- Team update: `PUT /admin/team/{role}/{id}`
- Team delete: `DELETE /admin/team/{role}/{id}`

Valid roles used here:

- `COADMIN`
- `WORKER`

### Bookings

Purpose:

- Inspect booking records and update booking state.

Routes:

- View page: `GET /admin/bookings`
- JSON list: `GET /admin/bookings/json`
- Update status: `POST /admin/bookings/{id}/status`

### Payments

Purpose:

- Track payment state from bookings and mark items as paid.

Routes:

- View page: `GET /admin/payments`
- JSON list: `GET /admin/payments/json`
- Mark paid: `POST /admin/payments/{id}/mark-paid`

### Coupons

Purpose:

- Create and manage discount codes.

Routes:

- View page: `GET /admin/coupons`
- JSON list: `GET /admin/coupons/json`
- Create: `POST /admin/coupons`
- Update: `PUT /admin/coupons/{id}`
- Delete: `DELETE /admin/coupons/{id}`

### Payouts

Purpose:

- Create payout requests from bookings and mark them as processed.

Routes:

- View page: `GET /admin/payouts`
- JSON list: `GET /admin/payouts/json`
- Create payout: `POST /admin/payouts`
- Process payout: `POST /admin/payouts/{id}/process`

### Users

Purpose:

- View platform users and control account status.

Routes:

- View page: `GET /admin/users`
- JSON list: `GET /admin/users/json`
- Suspend: `POST /admin/users/{id}/suspend`
- Reactivate: `POST /admin/users/{id}/reactivate`

### Operations and finance pages

These dashboard pages already exist as placeholders or simple views and are meant for future expansion:

- `GET /admin/scan`
- `GET /admin/payments`
- `GET /admin/payouts`
- `GET /admin/withdraw`
- `GET /admin/settings`
- `GET /admin/cities`

### Export endpoints

CSV exports are available for common admin datasets.

Routes:

- Bookings export: `GET /admin/export/bookings`
- Payments export: `GET /admin/export/payments`
- Users export: `GET /admin/export/users`

## 7. Partner dashboard structure

The partner portal is intentionally narrower than admin.

### Main partner landing page

- Route: `GET /partner`
- Name: `partner.dashboard`

### Partner features

- Events
- GameHub
- Workers

Routes:

- `GET /partner/events`
- `GET /partner/gamehub`
- `GET /partner/workers`

Partner routes are protected by:

- `auth`
- `EnsureRole:PARTNER`

## 8. Route groups and access rules

### Public site

- Homepage, events pages, GameHub pages, profile, login, register, and public API endpoints.

### ERP portal

- Internal portal entry point under `/erp`.
- Protected by the ERP key middleware.

### Admin area

- Prefix: `/admin`
- Name prefix: `admin.`
- Roles allowed: `ADMIN`, `COADMIN`

### Partner area

- Prefix: `/partner`
- Name prefix: `partner.`
- Role allowed: `PARTNER`

## 9. Data and models used by the dashboard

Main models involved in the dashboard flow:

- `User`
- `Event`
- `Booking`
- `Coupon`
- `Payout`
- `AdminAction`

## 10. Audit and logging

Admin actions are recorded in `admin_actions`.

Examples logged:

- Coupon create/update/delete
- Booking status updates
- Payment mark-paid actions
- User suspend/reactivate actions
- Payout create/process actions
- CSV export generation

## 11. Important development notes

- The ERP key default is only for local development.
- Change the ERP key before using this in any shared or production environment.
- The dashboard uses Blade views plus small fetch-based scripts for CRUD interactions.
- Some existing pages are intentionally lightweight and can be expanded later with richer forms, filters, and pagination.

## 12. Quick start summary

```bash
php artisan serve
php artisan db:seed --class=AdminUserSeeder
```

Then open:

```text
http://127.0.0.1:8000/erp?key=letmein
```

Login with:

- `admin@local` / `password`

## 13. Suggested next improvements

If you want this dashboard to feel complete, the next useful additions are:

1. Pagination and search on every list page.
2. Better validation error display on all create/edit forms.
3. A dedicated audit log viewer page using `admin_actions`.
4. Stronger auth hardening with email verification and rate limiting.
5. Richer payout and finance workflows with status transitions.

## 14. Enterprise Upgrade Direction (University/ERP Scale)

The current dashboard is good for startup scale. To evolve into a large organization system, move from role-only checks to permission and organization scoped access.

Target shift:

- From: basic role checks (`ADMIN`, `COADMIN`, `WORKER`, `PARTNER`)
- To: hierarchical organization architecture with dynamic permissions and approval workflows

### Target role hierarchy (reference)

```text
SUPER ADMIN
PLATFORM ADMIN
FINANCE HEAD
OPERATIONS HEAD
EVENT HEAD
GAMEHUB HEAD
SECURITY HEAD
HR HEAD
STATE MANAGER
DISTRICT MANAGER
AREA MANAGER
VENUE MANAGER
STAFF
SCANNER
REFEREE
SUPPORT
PARTNER
PARTNER MANAGER
PARTNER STAFF
PARTNER ACCOUNTANT
AUDITOR
```

## 15. Access Model to Implement

### A. Hierarchical visibility

Users should only see data in their assigned organization scope.

Examples:

- State manager: only records under their state
- District manager: only records in their district
- Venue manager: only records in their venue
- Partner roles: only records mapped to that partner

### B. Permission-based authorization

Avoid hardcoding access as `if role == X` in controllers/views.

Use permission checks like:

```php
$user->can('events.create')
```

Recommended permission keys:

- `events.create`
- `events.edit`
- `events.delete`
- `events.approve`
- `gamehub.manage`
- `finance.view`
- `finance.edit`
- `finance.export`
- `users.suspend`
- `users.edit`
- `partners.approve`
- `workers.assign`
- `workers.remove`
- `analytics.view`

## 16. Database Blueprint for Enterprise Mode

Add these tables in the next phase.

### `roles`

- `id`
- `name`
- `slug`
- `level`

### `permissions`

- `id`
- `name`
- `slug`
- `module`

### `role_permissions`

- `role_id`
- `permission_id`

### `organization_units`

- `id`
- `name`
- `type` (`STATE`, `DISTRICT`, `AREA`, `VENUE`, `DEPARTMENT`)
- `parent_id` (for hierarchy tree)

### `user_organization_map`

- `user_id`
- `organization_id`
- `designation`

### `approval_workflows` (planned)

- `id`
- `module`
- `entity_type`
- `entity_id`
- `status`
- `current_step`

### `workflow_steps` (planned)

- `workflow_id`
- `step_order`
- `approver_role`
- `approved_by`
- `approved_at`

## 17. Recommended Laravel Stack (Enterprise Track)

Backend:

- Laravel (current project)
- Spatie Permission package
- Laravel Policies + Gates
- Queues for approvals/notifications
- Events and listeners for audit trails

Frontend direction:

- Current: Blade + fetch scripts (already in place)
- Next: Blade + Alpine.js + Tailwind for enterprise UI consistency
- Optional future: API-first backend + Next.js frontend

## 18. Module Roadmap

### Phase 1 (now)

- Role + permission engine
- Organization hierarchy
- Scoped data access
- Access control center UI

### Phase 2

- Approval workflow engine
- Full audit center (old value/new value, actor, IP, device)
- Notifications center
- Operational analytics

### Phase 3

- Multi-state scaling
- Live operations dashboard
- AI-assisted analytics and anomaly alerts

## 19. Sidebar Strategy by Role

### Super Admin

- Dashboard
- Organization
- Users
- Roles and Permissions
- Events
- GameHub
- Partners
- Finance
- Approvals
- Audit Logs
- Analytics
- Security
- Settings

### District Manager

- Dashboard
- District Events
- Workers
- Venues
- Reports

### Partner

- Dashboard
- My Events
- GameHub
- Workers
- Finance

## 20. Immediate Implementation Checklist (for this codebase)

1. Install and configure Spatie Permission.
2. Replace direct role conditionals with `can()` checks and policies.
3. Add `organization_units` and `user_organization_map` migrations.
4. Add scope filters in listing endpoints (`events`, `bookings`, `payments`, `users`).
5. Build admin screens for roles, permissions, and organization tree.
6. Add approval tables and a first workflow (`payout` or `partner approval`).
7. Expand `admin_actions` into structured audit logs with before/after payloads.

This is the architecture path to make the platform operate like a serious university/corporate ERP instead of a basic admin panel.
