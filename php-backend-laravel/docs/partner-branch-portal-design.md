# Partner portal — business, branches & capabilities

Design spec for turning `/partner` and `android-app/partner-app` from a
**single-venue** console into a **multi-branch** one, and for onboarding cafés as
the first non-sports business type.

Companion to `partner-venue-lane-design.md`, which designed the venue lane's
*content*. This one designs its *shape*.

---

## 1. The shape

```
BUSINESS  (users row, role=PARTNER)
   │  business_type: sports | cafe | event_venue | other
   │  capabilities:  bookings · events · resources · memberships · offers
   │
   ├── Branch — Koramangala      (venues row, kind=cafe)
   │      Resources · Bookings · Events · Offers · Staff · Cash
   ├── Branch — HSR
   └── Branch — Indiranagar
```

Three rules that follow from it:

1. **A branch is a `venues` row.** Not a new table.
2. **Capabilities live on the branch**, with a business-level default.
3. **The branch is the security boundary**, enforced server-side.

---

## 2. Branches are venues — decided

Everything operational already keys on `venue_id`: `bookings`,
`booking_payments`, `venue_blocks`, `venue_courts`, `venue_slots`,
`venue_reviews`, `venue_packages`, cash close-out, waitlist. A separate
`branches` table forks all nine and leaves two of every screen to maintain.

The work is smaller than it looks, because the multi-branch spine is already
there and unused:

| Already true | Evidence |
|---|---|
| A partner owns N venues | `venues.partner_id`, no unique constraint |
| Overview sums across all of them | `PartnerController::overview()` — `$venueIds` is a collection, not an id |
| Every operational route is branch-addressed | `routes/api.php:409-434` — `/venues/{id}/day`, `/{id}/bookings`, `/{id}/courts`, `/{id}/block` |
| Passes can already be chain-wide | `venue_packages.venue_id` nullable = "any of this partner's venues" |

**Nothing in the read path needs restructuring.** The branch switcher passes an
id to routes that already accept one. What is missing is the *dimension* — a
switcher, a rollup, and branch attribution on the three things that lose it.

### New columns on `venues`

```php
$table->string('kind')->default('sports');   // sports | cafe | event_venue
$table->string('branch_label')->nullable();  // "Koramangala"
$table->string('branch_code', 24)->nullable(); // "BB-KOR", desk + reports
$table->json('capabilities')->nullable();    // null = inherit from business
```

`venues.name` stays the public brand name. `branch_label` is what the switcher
and every internal table shows, so "Big Bean Coffee" doesn't repeat three times
down a column.

---

## 3. One type, three lanes

> **Superseded 2026-08-15.** This section originally split `partner_type` (which
> console mounts) from a new `business_type` (what the business runs), and routed
> cafés into the **gamehub** lane. Both halves were wrong, and are recorded here
> because the reasoning is the useful part.
>
> **The two columns were one fact.** A second column can disagree with the first,
> and this pair was guaranteed to — the admin's partner form only ever offered
> "Venue owner" and "Event organiser", so `business_type` was backfilled once and
> then sat frozen while `partner_type` moved. It was not merely redundant, it was
> *unwritable*: there was no way to create a café partner at all.
>
> **A café is not the sports console.** Routing `cafe → gamehub` meant a café
> owner reading "Courts", occupancy-by-sport and turf language — somebody else's
> business wearing their name.

`users.partner_type` is the single dimension. It picks the console **and** the
capability preset:

| Type | Label | Lane | Capabilities |
|---|---|---|---|
| `event` | Event host | `events` | events, offers |
| `venue` | Sports venue | `gamehub` | bookings, resources, memberships, offers |
| `cafe` | Café venue | `cafe` | bookings, resources, **events**, memberships, offers |

`App\Support\PartnerLane` owns the lane list, the type→lane map, the admin
labels, and each lane's vocabulary. `users.business_type` is dropped
(`2026_08_15_000200`), promoting any café identified by it first.

### Stored values are deliberately not renamed

They stay `event` / `venue` / `cafe`, labelled "Event host" / "Sports venue" /
"Café venue". The published APK reads `partner.type` off the API and maps
`'event'` to its events tab set, so renaming to `event_host` / `sports_venue`
would drop every already-installed copy into the wrong console until users
updated. The labels carry the meaning; the column only has to be stable.

### The café lane could not ship empty

`Dashboard::getWidgets()` falls through to the event list, and all ten of those
self-gate on `partner_type === 'event'` — so **any lane without an explicit
branch renders nothing**. That is how the venue lane shipped hollow once (§2).
Splitting the café lane without giving it widgets would have repeated it
exactly, which is why both landed in one change.

The café home shares the branch-lane widgets with a sports venue — the money and
booking arithmetic is identical — and differs by what's added and what's absent:

- **Shared, noun-resolved**: `VenueTodayWidget` reads "table-hours" not
  "court-hours"; `VenueUpcomingWidget` labels its column "Table".
- **Added**: `CafeWhatsOnWidget` — the nights, with tickets sold and revenue.
- **Absent**: nothing sports-shaped.

Forking four widgets to change one word would mean every future fix made twice,
with one copy rotting. The noun resolves through `PartnerLane::resourceNoun()`;
asking the events lane for one throws rather than inventing a word.

## 3b. Original reasoning (superseded)

`users.partner_type` cannot simply be replaced — it is load-bearing in two
places that gate access, not presentation:

- `User::canManage()` (app/Models/User.php:92) — `'gamehub' => partner_type !== 'event'`
- `ScopesToVenueLane::canView()` (app/Filament/.../ScopesToVenueLane.php:46) — same test

Both are **negative tests against `'event'`**, so adding `'cafe'` makes a café
silently inherit the entire venue lane. That is roughly the right destination,
but arriving there by accident means no one ever decides what a café should
*not* see.

### The migration

```php
users.business_type   sports | cafe | event_venue | other   // what they run
users.capabilities    json, nullable                         // business default
users.partner_type    KEPT — the lane router, nothing more
```

`partner_type` narrows to one job: which console mounts at login (`event` vs
everything else). Backfill `business_type` from it (`event` → `event_venue`,
else `sports`), then flip both gates from `!== 'event'` to an explicit
`in_array($type, [...], true)` so the set is written down rather than implied.

### Capabilities are derived, then overridden

Free-toggling six booleans per partner gives 64 configurations and a support
queue. Instead:

```
business_type  →  preset capability set  →  per-branch override (json diff)

sports       bookings, resources, memberships, offers
cafe         bookings, resources, events, memberships, offers
event_venue  events, offers
```

Capabilities belong on the **branch**, not only the business — one Big Bean
outlet has six PS5s and a stage, another is a twelve-seat coffee counter that
takes no bookings at all. `venues.capabilities` null means "inherit"; a value
means "this branch differs". A branch with `events` off simply has no Events
item in its nav.

---

## 4. The security boundary — `User::branches()`

The most important item in P0, and the one thing that must not be deferred.

**Correction to the original plan: no new pivot was needed.** `staff_venues`
already exists (`2026_07_23_000001_create_staff_assignment_tables.php`), with an
`assignedVenues()` relation and a `scopedVenueIds()` reader on User. It was
built for per-staff assignment and then enforced in only three places —
`checkInByCode`, `BookingService::cancelAsPartner`, and `PartnerReviews`.

Everywhere else, `PartnerController` scoped on `partner_id` alone, which means
*any branch of the business*. A desk person assigned to Koramangala could fetch
`/venues/{HSR_id}/day`, block its dates, and read its payment state.

The gap was the *shape* of the existing helper. `scopedVenueIds()` returns
`null` for "unrestricted", which is right for checking a booking already
loaded, and wrong for the ~20 places that fetch a branch **by id** — forget the
null case there and it fails open.

```php
/** Every branch this user may act on. Always a query; never null. */
public function branches(): Builder
{
    $query = Venue::query()->where('partner_id', $this->effectivePartnerId());
    $assigned = $this->scopedVenueIds();

    return $assigned === null ? $query : $query->whereIn('id', $assigned);
}
```

Every by-id fetch in `PartnerController` now goes through one private
`branch($request, $id)` that calls `branches()->findOrFail($id)`. A branch the
caller may not touch does not exist for them — **404, not 403**, since 403 would
confirm the id belongs to a sibling branch.

> The frontend switcher is a convenience. It is never the check.

**Altitude is derived, not stored.** `users.staff_role` turned out to be
unnecessary: owner = not desk staff, manager = desk staff holding all four
permissions, desk = anything less. `User::partnerAltitude()` reads the two facts
that are already authoritative rather than adding a third to keep in sync.

---

## 5. Memberships — the branch attribution gap

Verified in the current schema. The *offer* is already chain-capable; the
*ledger* is not:

| | Today | Needed |
|---|---|---|
| `venue_packages.venue_id` | nullable — null = any branch | ✅ works as-is |
| `customer_packages` | no venue column | `sold_at_venue_id` |
| `package_redemptions` | `booking_id` only, nullable | `redeemed_at_venue_id` |
| `packageHolder()` | ignores the package's branch lock | filter by `scopedVenueIds()` |

The last row is a live bug the moment a second branch exists: a
₹999 pass sold as *Koramangala only* is returned as usable by the HSR desk,
because the holder query filters on `partner_id` alone
(PartnerController.php:809). A walk-in redemption has no booking, so without
`redeemed_at_venue_id` the branch that gave away the session is unrecoverable —
and branch P&L, staff incentives and inter-branch settlement all depend on it.

Two states are enough for P0 — **branch-locked** (`venue_id` set) or
**chain-wide** (null). A subset ("Koramangala + HSR, not Indiranagar") needs a
`venue_package_venues` pivot; build it when a partner asks, not before.

---

## 6. Three altitudes

Nav keys off **altitude**, never `business_type`. Same portal, same components.

### 🟢 Owner — all branches

Lands on **Business Overview**. Headline numbers, then immediately the
comparison — because "which branch is soft this week" is the actual question and
a merged total cannot answer it.

| Branch | Revenue | Bookings | Utilisation |
|---|---:|---:|---:|
| Koramangala | ₹1.82L | 342 | 78% |
| HSR | ₹1.14L | 218 | 64% |
| Indiranagar | ₹1.86L | 401 | 86% |

Utilisation is the honest column: revenue rewards the biggest branch, occupancy
against capacity shows the one that is actually underperforming.

### 🔵 Branch Manager — one branch, all powers

Lands on **Today's Operations** for their branch. Bookings, upcoming, walk-ins,
events, today's takings, resources occupied. Actions:
New Booking · Walk-in · Check-in · Create Event · Block Resource.

### 🟡 Desk Staff — one branch, this shift

**Today's Desk** and nothing else. Upcoming bookings, walk-ins, check-ins,
cancellations, resource availability. No revenue analytics, no payouts, no
business settings, no other branch, no staff management.

### The switcher

```
HARAAN PARTNER      Big Bean Coffee ▾      All Branches ▾
```

Pinned in the topbar on every screen, persisted per user. Switching is instant
and global — never `Settings → Branches → Koramangala → Dashboard`. For a
manager or desk person the control renders as a static branch name (or a
2-item picker), not a disabled dropdown teasing branches they cannot open.

### Nav map

| Business level | Branch level |
|---|---|
| Overview · Customers · Memberships · Staff · Analytics · Payouts · Business Settings | Today's Desk · Bookings · Calendar · Resources · Events · Offers · Reviews · Cash · Branch Settings |

Customers stay business-level and keyed on phone — one person across all
branches. `customers()` already scopes on `partner_id`, so this is correct
today; the fix is only to *add* a branch breakdown, never to split the record.

---

## 7. What a café sells

Not food. **Experiences.** F&B is P3 at the earliest, and shipping it early
would put Haraan into a Zomato/Swiggy comparison it does not need.

**A. Bookable resources** — PS5, pool, snooker, board-game table, gaming zone,
private room, karaoke. `venue_courts` is already exactly this: a physical thing
that holds one booking at a time, with `sports` json, its own `price`, and
peak pricing. Generalise the column's *meaning* to "activities", relabel to
"Tables"/"Stations" per `kind`, and leave the table alone. The double-booking
rule the courts migration was written to protect is the same rule a pool table
needs.

**B. Simple table reservation** — "Table 04 · 4 seats · ₹200/hr · 6–10 PM".
A resource with a `seats` count, priced by the hour like everything else.
Explicitly *not* a restaurant reservation system; party-size-and-duration
(`Party of 4, 7:30 PM, 90 mins`) is a later shape, and pretending otherwise
turns P2 into a covers engine.

**C. Events** — open mic, quiz night, stand-up, workshops, live music. The
strongest fit: the café becomes a venue hosting Haraan experiences, and the
entire events lane already exists. This is the reason a café is on Haraan at
all rather than on a booking widget.

**D. Memberships** — the gaming pass. Already built; needs §5.

---

## 8. API changes

The read surface barely moves. Branch-addressed routes are untouched; the
business-level ones gain an optional filter.

```
NEW   GET  /api/partner/context      business, capabilities, branches[], scope, altitude
NEW   GET  /api/partner/branches/compare      revenue · bookings · utilisation per branch

+arg  GET  /api/partner/overview     ?venue_id=   (absent = all branches)
+arg  GET  /api/partner/bookings     ?venue_id=
+arg  GET  /api/partner/customers    ?venue_id=
+arg  GET  /api/partner/packages     ?venue_id=
+arg  GET  /api/partner/payouts      ?venue_id=   (attribution only; one account)
+arg  POST /api/partner/staff        venue_ids[], staff_role
```

There is no `/api/partner/me` today despite the older doc referencing one —
`/context` is that endpoint, and the app should read `branches[]` and
`altitude` from it to pick its nav graph, exactly as the lane graph is picked
now.

Payouts stay **one settlement account per business**. Add the branch column to
the statement first; per-branch bank accounts is a franchise feature and can
wait for a franchise.

---

## 9. Landmines this must respect

Drawn from what has already bitten this repo:

- **Never read `Venue::`/`Booking::` directly in a widget.** Go through the
  resource query, or platform-wide totals leak into a tenant — fixed twice
  already (Events KPI header, GameHubStatsWidget). `scopedVenueIds()` is the
  branch-level version of the same rule.
- **`lower(status)` everywhere.** Status columns are mixed-case; any new
  branch rollup that filters on status must lower both sides.
- **Filament v4 custom widgets need `$isLazy = false`** or the comparison table
  renders blank.
- **`partner.can:` middleware is capability-only.** It answers *may this person
  price things*, never *at which branch* — the branch check belongs in the
  query, not the route.

---

## 10. Phases

**P0.5 — Café lane.** ✅ **DEPLOYED** 2026-08-15. `PartnerLane` (lane list,
type→lane map, labels, per-lane nouns); `partner_type` gains `cafe` → its own
`cafe` lane; `users.business_type` dropped; café widget set + three-way
`Dashboard` routing; admin offers all three types; `/api/partner/context`
returns one `type` + derived `lane`; Android `Lane.CAFE` with its own tiles,
tab labels and "table" noun. Verified on prod across all three lanes —
café 7 widgets / table-hours, sports 6 / court-hours, host 9.

**P0 — Foundation.** ✅ **DEPLOYED** to prod 2026-08-15.

*Data* — `venues.kind` + `branch_label`/`branch_code`/`capabilities`,
`users.business_type` + `capabilities`, `sold_at_venue_id` +
`redeemed_at_venue_id`.

*Boundary* — `User::branches()` threaded through all 16 venue-scoped sites in
PartnerController; the holder-query branch lock; explicit lane map.

*API* — `/api/partner/context`, `?venue_id=` on overview / bookings / customers /
packages / holder, `venueId` on package sale.

*Switcher* — web: `PartnerBranchContext` (session), `POST /partner/branch`,
TOPBAR_START render hook, `ScopesToVenueLane` honours the selection so the whole
dashboard moves with it. App: `PartnerContext` + branch chip in the shell topbar,
selection persisted, threaded into every scoped call.

*Tests* — 27 across `PartnerBranchScopingTest` (16) and
`PartnerBranchSwitcherTest` (11).

**Two rules the switcher follows on both surfaces.** It renders only above one
branch, so today's single-venue partners see no change at all. And a selection
that goes stale — branch deactivated, desk person reassigned — degrades to "all
branches" rather than locking someone out of a console they still belong in.

**P1 — Chain owner.** Branch comparison table, revenue/bookings/utilisation,
underperforming-branch alerts. *Ships as: the owner intelligence layer.*

**P2 — Café operations.** 🟡 **Partly deployed** 2026-08-15 (web).

✅ *Shipped* — `venue_courts.seats` + `.kind` (court | table | station | room |
lane), so a unit can finally say "Table 04 · 4 seats" instead of being a
nameless court; **Today's Desk** at `/partner/desk` — walk-in-first, live
free/busy per unit, still-to-come list, and a seat-a-walk-in sheet that goes
through `BookingService` so the desk obeys the same conflict rule as every
other booking path. Party size is a soft guard: a unit with no stated capacity
never blocks on it.

The desk belongs to ONE branch and refuses to guess — on "All branches" it asks
which floor you're standing on, because seating someone at the wrong outlet is
worse than one extra click.

⬜ *Remaining* — reservation-by-party-size (`Party of 4, 7:30 PM, 90 mins`),
check-in from the desk, and the Android desk.

**P2 — original scope.** Walk-in-first desk, resources generalised beyond
courts, availability, check-in, simple table reservation, café events.
*Ships as: a café can run its floor on Haraan.*

**P3 — Advanced.** F&B ordering, menu, kitchen workflow, POS, per-branch
payouts, multi-brand ownership.

---

## 11. Open questions

1. **Multi-brand.** One owner, two brands (a café *and* a turf). The model here
   scopes the business to a `users` row, so two brands = two logins. A
   `partner_businesses` table between user and venue would fix it — worth doing
   only if that partner is real.
2. **Branch-level pricing autonomy.** Can a manager change their own prices, or
   does the business set them centrally? Franchise answer differs from
   company-owned answer; today `pricing` is one flat permission.
3. **Cross-branch redemption economics.** A pass sold at Koramangala and burned
   at HSR — does the money move between branches, or is it only reported?
   `redeemed_at_venue_id` makes either possible; the policy is a business call.
