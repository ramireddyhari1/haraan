# Venue partner — product spec

What the venue lane of `/partner` should do, why, and what it costs against the
schema we actually have. Feature scope only; the UI/design system lives in
[partner-venue-lane-design.md](partner-venue-lane-design.md).

Companion decision: venues are created and assigned by admins, never by partners
— see `VenueResource::canCreate()`.

---

## 1. The thesis

A venue owner sells **time**. Every rupee lost is one of four leaks:

1. **An empty slot** — nobody booked it.
2. **A slot booked but not paid** — advance taken, balance never collected; no-shows.
3. **Cash that never reached the owner** — collected at the desk, not banked.
4. **A regular group that quietly stopped coming** — the highest-LTV churn, invisible.

Organise the console around plugging those four. Everything else is reporting.

**Playo's partner dashboard is thin on purpose** — their value is demand
aggregation, not software. "More features than Playo" is the wrong target. The
winning position is: *the dashboard becomes the only place the business runs,
including the ~70% of bookings that arrive by WhatsApp and phone.* Playo
structurally cannot do that, because it only ever sees Playo bookings.

---

## 2. Scope decisions

Four of the seventeen proposed pages should not be built now. The remaining
thirteen get better for it.

| Cut / defer | Why |
|---|---|
| **Academy** | Different business, buyer and data model (students, guardians, batches, fee cycles, certificates). ~15% of venues run one, usually off the coach's own WhatsApp. Roughly doubles surface area for a minority. |
| **Payroll / salary** | Computing salary in India drags in PF, ESI, TDS and bonus rules — real compliance liability, zero differentiation. Keep shifts + attendance, export to whatever they already use. |
| **Inventory purchase orders** | A venue tracks ~15 SKUs. Supplier POs with due dates is ERP cosplay. Keep "what's rentable, what's low, what's broken." |
| **Tournaments** | Real demand, but it's a product, not a page — fixtures, brackets, registration, payments, live score, leaderboard. We already have `LiveMatch` + ActionBoard on the consumer side; a second scoring path would fragment it. Build it *on* ActionBoard, later. |
| **AI fraud detection, AI refund risk** | Theatre at 100 bookings/day of ₹1,400. Costs credibility every time they misfire. |

---

## 3. The eight additions that matter

Ranked. The first three are the product.

### 3.1 WhatsApp booking desk — the moat

Most turf bookings in India start as *"bro Saturday 8pm free hai?"* on WhatsApp.
Today that lives in a personal phone, so occupancy data is fiction and
double-bookings are routine.

One inbox showing incoming threads, a **convert-to-booking** action that checks
the live slot grid, and a Razorpay payment link sent back into the thread.

**Already have** — `channel_connections` (per-partner WhatsApp with
`access_token`, `status`, `last_error`), `message_templates` (with
`provider_template_id` + `status`, i.e. the Meta template-approval flow), and
crucially **`message_conversations` with `opened_at` / `expires_at` /
`message_count`** — that is the 24-hour session window a WhatsApp desk lives or
dies on. `bookings.channel` already exists as a dimension.
`bookings.guest_name` / `guest_phone` support a booking with no account.

**New** — thread ↔ booking linkage, an inbox read model, the convert action.
No new messaging infrastructure.

> This is the single highest-value item on the list.

### 3.2 Cash reconciliation & shift close-out

The quiet epidemic. Front desk takes ₹1,400 cash, marks it "paid at venue",
pockets some. Owners feel it and cannot prove it.

Every shift closes: system says ₹18,400 cash was collected, staff counts the
drawer, **variance is logged against a named person**. Owners will pay for this
alone, and nobody in the category does it well.

**Already have** — `PartnerStaff` + RBAC, `staff_permissions`, shift concept in
the staff surface.

**New** — `shift_sessions` (staff_id, venue_id, opened_at, closed_at,
expected_cash, counted_cash, variance, note) and a `collected_by` stamp on
payments taken at the desk.

### 3.3 Advance / partial payment as a first-class state

**₹500 advance, balance at the venue is the default booking in India**, not an
edge case.

This is a **real schema gap, not a UI gap**: `bookings` has `total_amount`,
`discount` and `convenience_fee` but **no `amount_paid` and no
`payment_status`**. Partial payment cannot currently be represented at all.

**New** — `bookings.amount_paid`, `bookings.payment_status`
(`unpaid|partial|paid|refunded|part_refunded`), and a `booking_payments` ledger
(amount, method, collected_by, collected_at, razorpay_payment_id) so cash,
UPI-at-venue and online each have a row. Balance-due reminders, one-tap collect
at the desk, and correct flow into settlement and GST all hang off this.

**Do this before Finance.** Bolting it on later means rewriting settlement.

### 3.4 Standing slots (recurring bookings)

"Same ten guys, every Tuesday 8pm" is the backbone of turf revenue and the
highest-LTV customer type. Playo handles it badly.

Treat it as a subscription: auto-renew, skip-a-week, auto-invoice, and a **churn
alert when a standing group misses two in a row**.

**Already have** — `bookings.recurring_group` exists. Half-modelled already.

**New** — a `recurring_bookings` parent (rule, court, weekday, time, price,
active_from/until, auto_renew) that generates children into `recurring_group`;
skip/pause; the missed-twice signal.

### 3.5 Waitlist with auto-fill on cancellation

Turns the biggest loss event into revenue. A 7pm Saturday cancels → WhatsApp the
three people who wanted it → first to pay gets it.

Trivially explainable ROI: *"we recovered ₹42,000 last month."*

**New** — `slot_waitlist` (venue, court, date, time window, user/phone,
created_at, notified_at, expires_at). Reuses the payment-link machinery from 3.1.

### 3.6 One conflict engine

Bookings, offline bookings, academy batches, maintenance blocks and tournaments
all consume the same **court-hour**. If each writes availability its own way we
*will* double-book — and one double-booking costs more trust than ten features buy.

**Already have** — `venue_courts`, `bookings.venue_court_id` + `start_time` /
`end_time`, and **`bookings.reserved_until`** (an existing hold/lock mechanism —
the foundation of an atomic reservation).

**Risk to design around** — `venue_slots` is a *weekly template* (`day` and
`time` are strings), not dated availability, and `venue_blocked_dates` is
**whole-date only**: it cannot express "Turf C, 09:00–14:00, Tuesday only." Both
need to resolve through one dated `court_hour` reservation layer that every
writer goes through.

### 3.7 Multi-venue roll-up

Owners have two to four properties; the ambitious ones have more. If the console
is single-venue, the best customers outgrow it.

Design the venue switcher and the consolidated P&L now, even if we ship one venue
first. Retrofitting a tenant dimension is the expensive kind of rewrite.

### 3.8 No-show handling

Track it per customer, surface a no-show rate, let the venue require a deposit
from repeat offenders. Cheapest revenue we will ever recover.

**Already have** — `checked_in_at` / `checked_in_count` give us the signal for free.

**New** — a `no_show` resolution on the booking and a rolling per-customer rate.

### Two smaller ones

- **Split payment** across a group — ten people splitting ₹4,400 is how this
  actually gets paid. Falls out of the `booking_payments` ledger in 3.3.
- **Customer khata** — regulars run a tab and settle monthly. If we don't model
  it they keep it in a notebook, and the notebook is the competitor.

---

## 4. Reshaping what's already on the list

**Pricing** — `venue_courts` has `peak_price` / `peak_days` / `peak_start` /
`peak_end`, so peak exists but weekend does not, and there is no slot-level
control. Go to a **`pricing_rules` table plus a visual week grid**: the owner
drags across 11am–4pm Tue–Thu and sets a rate. Then let AI *suggest* changes on
that same grid. One of the few places better UI directly creates money.

**Analytics** — twelve charts is furniture. Ship three questions:
*Where am I empty and what is it worth?* · *Who is about to stop coming?* ·
*Which court earns most per hour?*

**Marketing** — poster generation is a toy next to **audience-triggered
WhatsApp**: everyone who booked badminton in the last 60 days but not the last
21, sent an off-peak offer, revenue attributed back. `coupons` is already rich
enough to carry it (`restrict_dates`, `valid_times`, `eligibility`,
`per_customer_limit`, `min_order`, `max_discount`). Keep the generator, lead with
the audience.

**Finance** — the important half is not reports, it is **settlement trust**.
`payouts` is currently booking-level (`booking_id`, `amount`, `status`) with no
fee breakdown, and `payout_batches` carries only a total. Show exactly why a
settlement was ₹1,84,200 — gross, platform fee, refunds, adjustments, line by
line. *"Why is my payout short"* is the number-one partner support ticket in this
category; answering it before it is asked deletes a whole support queue.

---

## 5. AI, triaged honestly

| Build now | Build later | Cut |
|---|---|---|
| Revenue & occupancy forecast | Customer segmentation | Fraud detection |
| Low-occupancy detection **with a rupee value** | Weekly insight digest (a good WhatsApp retention hook for the *owner*) | Refund risk |
| Churn prediction on standing groups | Marketing copy generation | |
| Smart pricing suggestions on the grid | | |

**One rule:** every AI output names a rupee number and offers a one-tap action.

> "Thursday badminton 3–6pm is at 19%. A ₹150 off-peak rate recovers about
> ₹5,500/month — create that rule?"

That is useful. A confidence score is not.

---

## 6. Build order

Each phase ships something a venue owner can use on its own.

1. **Make it real** — bookings table, calendar on the conflict engine, courts +
   pricing grid, offline booking, advance/balance. Nothing else has data without this.
2. **Make it trusted** — payment states, cash close-out, settlement explainer,
   GST, invoices.
3. **Make it grow** — waitlist, standing slots, memberships, WhatsApp desk,
   audience campaigns.
4. **Make it smart** — forecasts, churn, pricing suggestions, weekly digest.
5. **Only then** — tournaments, academy, inventory, as separate modules, and only
   if customers ask.

---

## 7. Schema summary

Everything the above needs, in one place.

### Already exists — lean on it

| Column / table | Unlocks |
|---|---|
| `bookings.channel` | offline vs app vs WhatsApp attribution |
| `bookings.recurring_group` | standing slots (3.4) |
| `bookings.guest_name` / `guest_phone` | walk-ins with no account |
| `bookings.venue_court_id`, `start_time`, `end_time` | court-hour bookings |
| `bookings.reserved_until` | atomic holds → conflict engine (3.6) |
| `bookings.checked_in_at` | no-show signal (3.8) |
| `message_conversations.opened_at` / `expires_at` | WhatsApp 24h session window (3.1) |
| `channel_connections`, `message_templates` | per-partner WhatsApp + Meta template approval |
| `coupons.*` (dates, times, eligibility, limits) | audience campaigns |
| `PartnerStaff` + `staff_permissions` | who collected the cash (3.2) |

### New — the real cost

| Change | For |
|---|---|
| `bookings.amount_paid`, `bookings.payment_status` | 3.3 — **blocks Finance** |
| `booking_payments` ledger (+ `collected_by`) | 3.3, 3.2, split payment |
| `shift_sessions` (expected vs counted cash, variance) | 3.2 |
| `pricing_rules` (dated, court+time scoped) | §4 pricing grid |
| dated `court_hour` reservation layer | 3.6 — `venue_slots` is a weekly template, `venue_blocked_dates` is whole-date only |
| `recurring_bookings` parent | 3.4 |
| `slot_waitlist` | 3.5 |
| `no_show` resolution + per-customer rate | 3.8 |
| settlement line items on `payouts` / `payout_batches` | §4 settlement trust |

---

## 8. The wedge nobody else has

Haraan owns the consumer side: GameHub, ActionBoard, match scoring, player stats,
XP and district rankings. Playo has games; it has nothing like our scoring depth.

That lets us offer a venue something no competitor can: **matches played at your
turf become part of players' permanent record** — their stats, their XP, their
ranking. The venue gets a leaderboard, a house-of-record identity, and a
retention loop with nothing to do with price.

For a turf owner competing on ₹100 differences, that is the only durable answer.
It belongs in the venue console as a first-class surface, not an afterthought.
