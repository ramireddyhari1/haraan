# Actionboard — How It Works

## Purpose

This document explains how Actionboard works, the main components, data flow, user roles, and how to integrate or run reports.

## Key Components

- **Actionboard UI**: Web interface for viewing and managing action items.
- **Backend API**: REST API that reads/writes action items, users, comments, attachments.
- **Database**: Persistent store (Postgres/MySQL/SQLite) for actions, users, history.
- **Auth & ACL**: OAuth2 / JWT authentication and role-based access control.
- **Notifications**: Email, in-app, and webhook notifications for updates.
- **Integrations**: Connectors for Slack, Teams, GitHub, Jira, and calendar systems.

## Data Model (simplified)

- Action: id, title, description, owner_id, status, priority, due_date, tags, created_at, updated_at
- User: id, name, email, role
- Comment: id, action_id, user_id, body, created_at
- Attachment: id, action_id, filename, url

## Data Flow

1. User creates an action in the UI → UI calls `POST /api/actions`.
2. Backend validates input, applies ACL, saves to DB, emits `action.created` event.
3. Notification service sends email/webhook and pushes in-app notification.
4. Assigned owner updates status → backend records change and appends history.
5. Reporting queries aggregated metrics and renders dashboards or exports.

## User Roles & Permissions

- **Admin**: full access, manage users and integrations.
- **Manager**: create and assign actions, view team reports.
- **Contributor**: create/modify actions they own, comment.
- **Viewer**: read-only access to boards and reports.

## Typical Workflows

- Create an action: fill title/desc, set owner, priority, and due date.
- Assign & track: owner receives notification and updates progress.
- Escalate: if overdue, escalate via configurable rules (email + manager ping).
- Close: owner marks complete; system records completion time and triggers follow-ups.

## API Examples

Create action (request):

```json
POST /api/actions
{
  "title": "Prepare Q2 report",
  "description": "Collect metrics and write summary",
  "owner_id": 42,
  "priority": "high",
  "due_date": "2026-06-10"
}
```

Get board (response):

```json
GET /api/boards/123
{
  "id": 123,
  "name": "Product",
  "columns": [
    {"name":"To do","actions":[/* ... */]},
    {"name":"In progress","actions":[/* ... */]},
    {"name":"Done","actions":[/* ... */]}
  ]
}
```

## Metrics & Reports

- Open actions by owner
- Average time to close
- Overdue actions
- Actions by priority and tag

These metrics are computed from the actions history and exposed via `/api/reports`.

## Security & Compliance

- Use secure transport (HTTPS).  
- Store sensitive data encrypted at rest.  
- Audit logs for action changes and access.  
- Role-based access control and 2FA for Admins.

## Integration Tips

- Webhook payload (action change):

```json
{
  "event": "action.updated",
  "action": { "id": 101, "status": "done" },
  "changed_by": { "id": 42, "name": "Alex" }
}
```

- For calendar sync, send ICS invites when due dates are set.

## Deployment & Setup (high level)

1. Provision a database and set `DATABASE_URL`.
2. Configure `AUTH_PROVIDER` (OAuth2/JWT) and secrets.
3. Set `SMTP` for email and configure notification endpoints.
4. Start backend and frontend services; run DB migrations.

## FAQ

- Q: How are reminders scheduled?  
  A: Background worker processes recurring jobs, uses cron-style schedules per-action.
- Q: Can I export actions?  
  A: Yes — CSV and PDF export endpoints are available (`/api/actions/export`).

## Next Steps

- Review the integration endpoints and map to your existing tools.  
- Provide board-specific fields you need (custom fields, workflows).  
- I can produce a PDF version of this doc for distribution — say "generate PDF".
