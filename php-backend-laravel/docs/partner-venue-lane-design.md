# Partner console — venue lane design & handoff

Design spec for the **GameHub venue lane** of `/partner`, reached through the
same login as the events lane.

Interactive prototype: <https://claude.ai/code/artifact/14872683-141f-42d5-8391-19e94fc14d9c>
(design system, component library, all 17 venue surfaces, both lanes, light/dark,
desktop/tablet/mobile, loading/empty/data states.)

---

## 1. One door, two lanes

There is one partner login. Which console mounts behind it is a property of the
**account**, never of the URL.

```
POST /partner/login            (phone OTP · Google · email+password)
  ↓
User::canAccessPanel('partner')      app/Models/User.php:110
    · must hold PARTNER
    · must NOT be super-admin  (admins belong in /control)
    · must NOT be a suspended desk member
  ↓
users.partner_type
    'event'          → events lane   (canManage('events')  === true)
    'venue' | null   → venue lane    (canManage('gamehub') === true)
```

`User::canManage()` (app/Models/User.php:85) already implements this lane-lock.
**No routing work is required.** What is missing is the venue lane's content.

### Why not two logins

- A partner who runs a turf *and* ticketed leagues would need two passwords and
  two support histories.
- Two doors = two OTP flows, two Google client configs, two SEO surfaces; one of
  them always rots.
- With one door there is no wrong door for support to diagnose.

### The one open question: dual-lane accounts

`users.partner_type` is a single scalar, so an account is either a host or a
venue owner — never both. If a genuinely dual partner is a real customer, derive
the lane set from ownership and keep the column as the *default landing lane*:

```php
$lanes = array_filter([
    'gamehub' => Venue::where('partner_id', $u->id)->exists(),
    'events'  => Event::where('partner_id', $u->id)->exists(),
]);
```

The lane switcher then lives in the sidebar workspace switcher (already
prototyped). If dual partners are not a real segment, leave the column alone.

---

## 2. The gap being closed

`app/Filament/Widgets/Partner/` holds 12 widgets. Ten are hard-gated
`partner_type === 'event'`. Only `PartnerEarningsStatsWidget` and
`PartnerEarningsLedgerWidget` are visible to a venue owner.

**A venue partner signs in today and lands on a near-empty dashboard.** That is
the single highest-value thing to fix.

```php
// app/Filament/Pages/Dashboard.php — make getWidgets() lane-aware
protected function getWidgets(): array
{
    return auth()->user()?->partner_type === 'event'
        ? [ /* the 10 event widgets that exist today */ ]
        : [
            VenueQuickActionsWidget,   // blue launchpad hero (mirrors the event one)
            VenueKpiHeroWidget,        // revenue · bookings · occupancy · pending
            VenueSecondaryKpiWidget,   // cancellations · growth · ABV · repeat rate
            VenueRevenueTrendWidget,   // this week vs last week, one ₹ axis
            VenueOccupancyWidget,      // ring + per-surface meters
            VenueUpcomingWidget,       // next five reservations
            VenueAiInsightsWidget,     // low-occupancy / churn / expansion
            VenuePeakHoursWidget,      // hourly occupancy bars
            VenueActivityWidget,       // timeline
        ];
}
```

---

## 3. Reuse vs build

Read from the repository, not estimated.

| Surface | What already exists | Work |
|---|---|---|
| Dashboard | 2 of 12 widgets venue-visible | **8 new widgets** |
| Bookings | `Booking` model, slot grid | table, filters, bulk actions |
| Calendar | `VenueSlot`, `VenueBlockedDate` | week grid + drag |
| Courts | `VenueCourt` (sport-aware) | pricing-rules layer |
| Customers | `User` + booking history | LTV / churn rollups |
| Finance | `Payout`, `PayoutBatch`, `PartnerPayoutAccount` | GST + invoices |
| Analytics | — | occupancy cube |
| Marketing | `Coupon`, `MessageTemplate`, `ChannelConnection` | poster generator |
| Memberships | — | new |
| Subscriptions | `PartnerPlan`, `PartnerSubscription` | **reuse as is** |
| Staff | `PartnerStaff` + RBAC | attendance, payroll |
| Inventory | — | new |
| Tournaments | `LiveMatch` (cricket) | fixtures, brackets |
| Academy | — | new |
| Reviews | `VenueReview` | sentiment, Google sync |
| Settings | `AutomationRule` | venue profile |
| Support | `SupportThread` + `PartnerSupportAI` | **reuse as is** |

### Build order

Each phase ships something a venue owner can use on its own.

1. **The hollow lane** — dashboard widgets, bookings table, calendar week grid.
   Closes the empty-dashboard gap.
2. **The money** — finance with GST + invoices, court pricing rules, memberships.
   Reuses the payout models already in place.
3. **The business** — analytics cube, marketing studio, customers with LTV.
   Needs phase 1 data to be meaningful.
4. **The depth** — staff attendance/payroll, inventory, tournaments, academy.
   Independent of each other; ship in whatever order the market asks.
5. **The app** — four screens, both lanes, off the endpoints phases 1–2 expose.

---

## 4. Design tokens

Drop into the compiled Filament theme. Dark mode is a **re-declaration of the
same token names**, never a filter or an inversion.

```css
:root{
  --brand-500:#0A66FF;  --brand-600:#1D4ED8;  --brand-700:#1740A6;
  --good:#16A34A;  --warn:#B45309;  --bad:#DC2626;
  --ground:#F7F9FC; --surface:#FFFFFF; --line:#E5E7EB;
  --ink:#0B1220;  --ink-2:#3D4759;  --ink-3:#6B7688;  --ink-4:#98A2B3;
  --r-lg:18px;  --s5:24px;
  --t:150ms;  --ease:cubic-bezier(.22,.61,.36,1);
}
```

Notes that matter in implementation:

- The neutrals carry an ~8° hue bias toward the brand blue, so the console reads
  as one temperature rather than a blue accent on a cold slab.
- `#22C55E` / `#F59E0B` / `#EF4444` are **fill** hues. Text and icons use the
  darker `--good-ink` / `--warn-ink` / `--bad-ink` steps — amber at full
  saturation fails contrast on white and must never carry text.
- 18px is the card radius; every smaller control steps down so a 30px chip never
  looks like a pill inside an 18px card.

### Chart palette — validated, not eyeballed

```
light   #0A66FF #0D9488 #F59E0B #8B5CF6 #DB2777    all checks PASS
dark    #4D8DFF #0EA396 #C4800B #9B7BF7 #EC4899    all checks PASS

worst adjacent pair (light)   amber ↔ teal   ΔE 16.6 protan
normal-vision floor           teal  ↔ blue   ΔE 24.1
contrast relief               amber is under 3:1 on white — always legended or
                              direct-labelled, never colour-alone
```

Rules that hold everywhere:

- Series colour follows the **entity**, never its rank. Filtering a chart to
  three series must not repaint the survivors.
- **One y-axis.** Two measures of different scale get two charts.
- Sequential = one hue light→dark. Diverging = two hues with a neutral midpoint.
- Semantic status colours are reserved and are never reused as "series 4".

---

## 5. Responsiveness

Driven by **container width, not device sniffing** — a `ResizeObserver` on the
app shell sets `is-narrow` (< 760px) and `is-mid` (760–1160px), so the same rules
run in a narrow browser window and inside a phone.

| | Behaviour |
|---|---|
| Sidebar | Off-canvas sheet behind the hamburger; 5-item bottom tab bar takes over |
| Grids | 4-up and 3-up collapse to 2-up; 2-up stacks to one column |
| Tables | Become card lists — never a horizontally scrolling table on a phone |
| Calendar | Week grid becomes a single-court day column with a court picker |
| Drawers | Become full-height bottom sheets with a drag handle |
| Type | Display 30→23px, metric 29→24px; body stays 14px |

---

## 6. Partner app

`android-app/partner-app` is three Kotlin files today (`MainActivity`,
`PartnerApi`, `PartnerApp`) — a shell with no screens. Both lanes are unbuilt, so
this is a clean slate rather than a migration.

Lane resolution uses the endpoint that already exists at
`app/Http/Controllers/Api/PartnerController.php:72`:

```jsonc
GET /api/partner/me
{ "type": "venue",           // users.partner_type
  "permissions": ["bookings","pricing","checkin", …],
  "venues": [ … ], "events": [] }
```

The app never asks what kind of partner you are — it reads `type` and picks the
nav graph. One APK, one login, two lanes.

**Four screens belong on a phone**, and only four:

1. Scan & check in — camera opens with the tab, works offline, queues scans.
2. Take a walk-in booking — three taps; everything else defaults.
3. Today's money — one number, one sparkline, one settlement status. No tables.
4. Act on an alert — push lands, one tap resolves it.

Tab bars: venue `Home · Calendar · Bookings · Money · More`,
events `Home · Events · Scan · Money · More`.

Analytics, marketing, payroll and tournaments stay on the web console. A venue
owner does not build a pricing rule on a phone, and pretending otherwise is how
partner apps end up as a worse browser.

---

## 7. Accessibility floor

Not a later pass.

- Body text ≥ 4.5:1, large text and UI ≥ 3:1, **in both themes**.
- A visible 3px brand focus ring on every interactive element.
- Never colour alone — a badge has a dot, a closed calendar block is hatched,
  every multi-series chart has a legend.
- `prefers-reduced-motion` collapses every animation to 0.001ms.
- `aria-current` on nav, `aria-pressed` on toggles, real table markup.
- Targets ≥ 30px on desktop, ≥ 44px on the mobile tab bar.
- Every chart carries an `aria-label` and has a table equivalent one tap away.
