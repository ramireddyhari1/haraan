# Haraan Control — Admin Guide

How to use everything in the **Haraan Control** panel built in Phases 0–2. This
covers logging in, roles & permissions, district scoping, and the runtime
"customize the app without a release" tools (feature flags, home layout,
branding, localization).

> **Where:** the admin panel lives at **`/control`** (e.g. `https://<your-host>/control`).
> It uses normal Filament login + MFA — it does **not** need the old `?key=`.
> The legacy `/admin/*` Blade pages now redirect into `/control`.

---

## 1. Logging in & who can get in

1. Go to `/control/login` and sign in with your admin email + password.
2. If your account has MFA enabled, you'll be prompted for the code.
3. **Who can access:** super-admins (legacy `ADMIN`/`COADMIN`) and department
   roles (`FINANCE`, `MARKETING`, `OPS`, `PARTNER`). Regular app users cannot.

What you see inside depends on your role (see next section). Super-admins see
everything; department roles see only their workspace cluster.

---

## 2. Roles & permissions (Filament Shield)

Roles are managed at **`/control/shield/roles`** (super-admins only).

- **`super_admin`** — full access to everything (bypasses all checks).
- **Department roles** (`FINANCE`, `MARKETING`, `OPS`, `PARTNER`, `WORKER`) —
  scoped to the resources their cluster manages.

**To create a new role:**
1. Roles → **New role**.
2. Name it (e.g. `Vellore City Manager`).
3. Tick the permissions it should have (per-resource: View / Create / Update /
   Delete). Permissions are named `Action:Entity`, e.g. `View:Venue`.
4. Save, then assign it to a user (Users → edit user → roles).

> **Note on legacy roles:** every user still has a legacy `role` string column
> that's kept in sync for backwards-compatibility. You normally don't need to
> touch it — assign Shield roles instead.

---

## 3. District / organization scoping

This is what lets a **district manager see only their district's data**.

### How it works
- There's an **organization tree**: `STATE > DISTRICT > AREA > VENUE`
  (manage at **`/control/organization-units`**).
- Records (venues, events, bookings, live matches, users) carry an
  **Organization** field.
- A staff user assigned to an org **only sees records within that org's
  subtree**. Super-admins and staff with **no org** see everything.

### To make a district manager
1. **Users → edit the user.**
2. Set the **Organization** field to their district (or assign via the
   **Organization memberships** tab for multi-district managers, with a
   designation like "City Manager").
3. Give them an appropriate role (Section 2).
4. Done — when they log in, Venues/Events/Bookings/Matches/Users are filtered to
   their district automatically.

> Players who complete their in-app profile are auto-assigned a home
> organization from their state/district — no manual step needed for them.

---

## 4. Feature flags (turn app features on/off, no release)

Manage at **`/control/feature-flags`** (super-admins). The app reads these on
launch via `GET /api/config`.

**Each flag has:**
| Field | What it does |
|---|---|
| **Key** | The machine name the app checks (e.g. `local_league_creation`). Set once. |
| **Enabled** | Master switch. Off = disabled for everyone. Toggle inline in the list. |
| **Rollout %** | Gradual rollout. `100` = everyone; `25` = a stable 25% of users. |
| **Target organizations** | Limit to specific districts (includes their subtree). Empty = everywhere. |
| **Min app version** | Hide from older app builds (e.g. `1.4.0`). |

**Typical uses:**
- Soft-launch a feature: set Enabled = on, Rollout = 10%, raise gradually.
- Pilot in one district: Enabled = on, Target = that district.
- Kill switch: flip Enabled = off to instantly disable everywhere.

---

## 5. Home layout (arrange the GameHub home, no release)

Manage at **`/control/marketing/home-blocks`** ("Home layout", Marketing role).
The app reads this via `GET /api/home/layout`.

The home screen is an **ordered list of blocks**. Each block is one section:
`hero`, `sports_chips`, `ad_strip`, `feed_section`, `venues`, `leaderboard`,
`actionboard`.

**To change the home:**
1. **Drag rows** to reorder sections.
2. Toggle **Active** to show/hide a section instantly.
3. **Config** holds type-specific params, e.g. a `feed_section` with
   `section = for_you`, or an `ad_strip` with `placement = home`.
4. **Gate behind feature flag** (optional) — the block only shows when that flag
   is on for the viewer.
5. **Target organizations** (optional) — show a block only in certain districts.
6. **Schedule** (optional) — `starts_at` / `ends_at` to run a block for a window
   (great for promos/tournaments).

---

## 6. Branding & theme (restyle the app, no release)

Manage at **`/control/branding-settings`** ("Branding & theme", super-admins).
The app reads this via `GET /api/config` → `theme`.

Editable: **app name, tagline, primary color, accent color, logo, support
WhatsApp.** Pick colors with the color picker, upload a logo, hit **Save** — the
app picks up the new theme on its next config fetch.

> Keep CTAs blue/green (progress/commit) per the design rule — the seeded
> defaults already do this.

---

## 7. Localization (edit app text & translations, no release)

Manage at **`/control/translations`** ("Localization", Marketing role). The app
reads this via `GET /api/i18n/{locale}`.

- Supported locales: **en, te, ta, kn, ml, hi.** English is the source.
- Each row is one **key + locale + value** (e.g. key `match_detail.live`,
  locale `te`, value `ప్రత్యక్షం`).
- **Edit values inline** right in the list; filter by **locale** or **group**.
- **Missing translations fall back to English automatically**, so a partly
  translated locale still works everywhere.

**To translate a string:**
1. Filter by the locale you're working on.
2. Find the key (or **New** to add one — set key, group, locale, value).
3. Type the translation in the **Value** cell and click away — it saves.

---

## 8. What the app reads (for reference)

| Endpoint | Purpose | Auth |
|---|---|---|
| `GET /api/config` | Feature flags (`features`) + branding (`theme`) | optional |
| `GET /api/home/layout` | Ordered home blocks | optional |
| `GET /api/i18n` | Supported locales | public |
| `GET /api/i18n/{locale}` | Translation bundle for a locale | public |

All resolve per-viewer when logged in (district targeting, % rollout). The app
should send its version as the **`X-App-Version`** header so version-gated flags
work.

---

## 9. Quick "I want to…" index

| I want to… | Go to |
|---|---|
| Add an admin / change someone's access | `/control` → Users, `/control/shield/roles` |
| Scope a manager to one district | Users → edit → Organization |
| Turn a feature on/off or roll it out | `/control/feature-flags` |
| Reorder / hide a home section | `/control/marketing/home-blocks` |
| Change app colors / logo / name | `/control/branding-settings` |
| Fix a typo or add a translation | `/control/translations` |
| Manage venues/events/bookings/matches | their resources under `/control` |
