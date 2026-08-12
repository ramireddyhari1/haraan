# Venue partner — phase 1 technical spec

The data foundation everything else in
[venue-partner-product-spec.md](venue-partner-product-spec.md) sits on: **money
state, a payment ledger, and one court-hour conflict engine.**

Nothing here changes a single number in an existing widget. That is a design
constraint, not an aspiration — see §6.

---

## 1. Why this has to happen now

Live prod, read 2026-07-30:

```
total bookings          10
  type:event             4      type:venue        6
  status:confirmed       4      cancelled 2   expired 4
has_razorpay_payment     0
zero_amount              0
payouts                  0
```

**Ten bookings, zero recorded payments, zero payouts.** There is no payment
history to migrate and no settlement to reconcile. Every month we wait, this
migration gets more expensive and more dangerous. Do it while the table is empty.

### The smell we are correcting

`bookings.status` is currently doing two jobs at once — lifecycle *and* payment
state. Revenue is derived by filtering status against a "paid set" that is
copy-pasted as a private const across at least six widgets:

```php
// PartnerEarningsStatsWidget.php:37
private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];
// FinanceStatsWidget, EventAnalyticsStatsWidget, EventSalesChartWidget, … same idea
```

So "confirmed" silently means "we got the money". For events bought through
Razorpay that happens to hold. For **venue bookings it is simply false** —
`BookingService::reserveVenue()` writes `'status' => 'CONFIRMED'` with no payment
step at all. A ₹4,400 turf booking with ₹500 advance and a ₹3,900 balance is
today indistinguishable from one paid in full.

Phase 1 separates the two concerns. It does **not** rewrite the widgets — that is
the follow-on prize in §7.

---

## 2. What phase 1 delivers

| | |
|---|---|
| **M1** | `bookings.amount_paid` + `bookings.payment_status` |
| **M2** | `booking_payments` ledger — every rupee, its method, and who took it |
| **M3** | `venue_blocks` + one court-hour conflict engine |
| **S1** | `BookingService` writes through the ledger and the new engine |

Out of scope, deliberately: pricing grid (§8), waitlist, standing slots, WhatsApp
desk. They all depend on this and none of them are blocked by anything else.

---

## 3. M1 — payment state on bookings

```php
Schema::table('bookings', function (Blueprint $table): void {
    $table->decimal('amount_paid', 10, 2)->default(0)->after('total_amount');
    $table->string('payment_status', 20)->default('unpaid')->after('amount_paid');
    $table->index(['venue_id', 'payment_status']);
});
```

`payment_status` ∈ `unpaid · partial · paid · refunded · part_refunded`.

Derived, never hand-set — see the invariant in §4.

### Backfill — the rule that matters

```php
// Everything the reporting layer currently counts as revenue must keep counting.
DB::table('bookings')
    ->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
    ->update([
        'amount_paid'    => DB::raw('total_amount'),
        'payment_status' => 'paid',
    ]);

// Cancelled / expired keep the default: unpaid, 0.
```

**Why `paid` and not something more honest:** the six widgets in §1 read status,
not payment state. If a currently-confirmed booking backfills to `unpaid`,
nothing changes today (they don't read the column) — but the moment §7 switches
them to the ledger, historical revenue would drop to zero. Backfilling to `paid`
makes that switch a no-op for existing rows. With 10 rows this is cosmetic; the
*rule* is what we are locking in.

No chunking needed at this size. Written as a single statement on purpose.

---

## 4. M2 — the `booking_payments` ledger

```php
Schema::create('booking_payments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
    // Signed: positive = money in, negative = refund. One SUM answers everything.
    $table->decimal('amount', 10, 2);
    $table->string('method', 20);              // cash | upi | card | online | wallet | adjustment
    $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('reference')->nullable();   // razorpay_payment_id, UPI ref, receipt no
    $table->string('note')->nullable();
    $table->timestamp('collected_at');
    $table->timestamps();

    $table->index(['booking_id', 'collected_at']);
    $table->index(['collected_by', 'collected_at']);   // shift close-out reads this
});
```

**Signed amount rather than a `direction` column.** A refund is `-1400.00`, so
`amount_paid` is one `SUM()` and can never disagree with itself because someone
forgot to read `direction`. Reporting that wants gross-in vs refunds-out filters
on sign.

### The invariant

```
bookings.amount_paid   == SUM(booking_payments.amount) for that booking
bookings.payment_status == f(amount_paid, total_amount, has_negative_rows)
```

Enforced in exactly one place — a `BookingPayment::saved/deleted` observer, or
better, a `BookingLedger::record()` service method that writes the row and
recomputes the parent inside the same transaction. **Never let a caller set
`amount_paid` directly.** Add a test that asserts the invariant across every
booking, so a future direct-write is caught.

Status derivation:

| condition | payment_status |
|---|---|
| `amount_paid <= 0` and no negative rows | `unpaid` |
| `0 < amount_paid < total_amount` | `partial` |
| `amount_paid >= total_amount` | `paid` |
| has negative rows and `amount_paid <= 0` | `refunded` |
| has negative rows and `amount_paid > 0` | `part_refunded` |

### What this unlocks immediately

- **Advance / balance-due** — the actual ask. `total_amount - amount_paid`.
- **Split payment** — ten rows against one booking, no new concept.
- **Cash reconciliation (phase 2)** — `collected_by` + `collected_at` is already
  the shift query; `shift_sessions` just adds the drawer count and the variance.
- **Settlement line items** — refunds stop being invisible.

---

## 5. M3 — one court-hour conflict engine

### What already works

`BookingService::reserveVenue()` is **already the single write path** for venue
bookings — online (`createVenueBooking`) and desk (`createOfflineVenueBooking`)
both funnel through it, inside `DB::transaction` with `Venue::lockForUpdate()`.
That venue-row lock is what makes the read-then-check in `assertWindowFree()`
safe against a race, and it is a better foundation than I expected.

`assertWindowFree()` already does correct court + `[start, end)` overlap, locking
a physical court across all its sports so football and cricket cannot be sold the
same ground.

### What is broken

1. **Only bookings participate.** Maintenance, academy batches, tournament blocks
   and private hires are not bookings, so they occupy nothing. This is the
   double-booking that costs more trust than ten features buy.
2. **`venue_blocked_dates` is whole-date only.** It cannot express *"Turf C,
   09:00–14:00, Tuesday"* — and it is checked in a separate branch from the
   overlap logic, so blocks and bookings can't reason about each other.
3. **The overlap query matches `lower(status) = 'confirmed'` only.** Fine today
   because venue bookings are born confirmed. The moment M1 lands and a booking
   can sit `unpaid`, an unpaid-but-real booking must still hold its court.

### The decision: `venue_blocks`, not a generic `court_reservations`

The textbook answer is one polymorphic reservation table that bookings, blocks
and batches all write to. **Reject it for phase 1.** It means migrating every
existing booking into a parallel table, keeping the two in sync forever, and
touching the one code path that currently works correctly. All risk, no phase-1
payoff.

Instead: bookings keep occupying court-hours as they do now, and **everything
that is not a booking** goes in one new table.

```php
Schema::create('venue_blocks', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
    // null court = the whole venue is blocked
    $table->foreignId('venue_court_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('kind', 20);                 // maintenance | holiday | academy | tournament | private
    $table->string('title')->nullable();
    $table->date('starts_on');
    $table->date('ends_on');                    // == starts_on for a single day
    $table->unsignedTinyInteger('weekday')->nullable();  // null = every day in range
    $table->string('start_time', 5)->nullable();         // null = whole day
    $table->string('end_time', 5)->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['venue_id', 'starts_on', 'ends_on']);
});
```

That single shape covers all four calendar block types in the brief: a one-off
maintenance window, a recurring academy batch (`weekday` + a date range), a
holiday (whole day, no court), and a tournament hold.

`venue_blocked_dates` **stays** — the partner API (`blockDate` / `unblockDate`)
and the app read it, and phase 1 does not touch clients. The engine reads both;
new UI writes only `venue_blocks`; a later migration folds the old table in and
retires it. Two sources is a deliberate, time-boxed cost, not an oversight.

### The engine

`assertWindowFree()` becomes `assertCourtHourFree()`:

```php
private function assertCourtHourFree(
    int $venueId, ?int $courtId, ?int $slotId, string $date, ?int $startMin, ?int $endMin
): void {
    $this->assertNoBookingOverlap($venueId, $courtId, $slotId, $date, $startMin, $endMin); // today's logic
    $this->assertNoBlockOverlap($venueId, $courtId, $date, $startMin, $endMin);            // new
}
```

Three rules for the new half:

- A block with `venue_court_id = null` blocks **every** court at that venue.
- A block with no `start_time` blocks the **whole day**.
- A block with `weekday` set applies only on that weekday within `[starts_on, ends_on]`.

And one correction to the existing half — occupancy is about lifecycle, not money:

```php
// was: ->whereRaw('lower(status) = ?', ['confirmed'])
->where(fn ($q) => $q
    ->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
    ->orWhere(fn ($h) => $h->whereRaw('lower(status) = ?', ['pending'])
                            ->where('reserved_until', '>', now())))
```

An unpaid booking still holds its court. A live hold still holds its court. An
expired hold does not. **`payment_status` must never appear in this query** — put
a comment on it, because it is exactly the mistake the next person will make.

---

## 6. What must not break

A hard checklist for review:

- **No existing widget changes its number.** Phase 1 adds columns and a table; it
  rewrites no reporting query. Verify by running the suite and eyeballing
  `PartnerEarningsStatsWidget` / `FinanceStatsWidget` totals before and after.
- **`reserveVenue()` keeps its signature.** Callers in `PartnerController`
  (`storeOfflineBooking`) and the consumer booking path stay untouched.
- **Venue bookings still succeed with no payment.** They simply land `unpaid`
  instead of implicitly-paid. The desk collects later.
- **`VenueBlockedDate` keeps working** for the app and partner API.
- **Event bookings are unaffected.** `createOrder()` already has its own money
  path via Razorpay; it gets a ledger row on confirm and nothing else changes.

---

## 7. The follow-on prize (not phase 1)

Once the ledger is trusted, switch the six duplicated `const PAID` widgets from
*"sum `total_amount` where status is in a paid set"* to *"sum `amount_paid`"*.

That one change makes partial payments, refunds and cash collection correct
**everywhere at once** — earnings, finance, event analytics, settlement — and
deletes six copies of a magic array. Do it as its own PR with before/after
numbers, not folded into phase 1.

---

## 8. Phase 1b sketch — pricing rules

`venue_courts` has `peak_price` / `peak_days` / `peak_start` / `peak_end`, so
peak exists, weekend does not, and nothing is slot-level.

```
pricing_rules
  venue_id, venue_court_id (null = all courts)
  weekdays (json), start_time, end_time
  mode: absolute | delta | percent
  amount, priority, active_from, active_until, is_active
```

Resolution: highest `priority` wins, first match applied — the same first-match
model the brief's "Pricing rules" table already describes. `VenueCourt::rateFor()`
becomes the one resolver and the peak columns migrate into rules.

Worth doing right after phase 1 because it is where a better UI directly makes
money — the owner drags across 11am–4pm Tue–Thu on a week grid and sets a rate.

---

## 9. Tests

| Test | Asserts |
|---|---|
| ledger invariant | `amount_paid == SUM(payments)` for every booking, after every write path |
| status derivation | each row of the §4 table, incl. refund and part-refund |
| advance then balance | ₹500 then ₹3,900 on a ₹4,400 booking → `partial` then `paid` |
| split payment | five rows, one booking, correct total |
| overlap: booking | existing behaviour preserved (regression) |
| overlap: block | whole-venue, whole-day, and weekday-recurring each reject |
| overlap: unpaid | an `unpaid` booking still blocks its court |
| overlap: expired hold | a `pending` booking past `reserved_until` does **not** block |
| backfill | a confirmed pre-migration booking reads `paid` / `amount_paid == total_amount` |
| widget parity | earnings + finance totals identical before and after the migration |

---

## 10. Deploy

Prod is SQLite in WAL mode. From the VPS runbook:

1. **Snapshot first** — `sqlite3 "$DB" ".backup /root/haraan-db-before-phase1-<ts>.sqlite"`.
   Use `.backup`, never `cp` — WAL means a plain copy can miss committed data.
2. Ship files by tar with `--owner=0 --group=0 --numeric-owner`, extract with
   `--no-same-owner`. A Windows-built tarball re-owns **directories** to uid
   197609, and a root-owned `database/` makes SQLite report
   *"attempt to write a readonly database"* even though the `.sqlite` file itself
   is fine — WAL needs to create `-wal`/`-shm` **inside** the directory.
   `database/` must stay `www-data:www-data 775`.
3. `php artisan migrate --force` as `www-data` with `HOME=/tmp XDG_CONFIG_HOME=/tmp`.
4. `filament:cache-components` + `view:clear`; `systemctl reload php8.3-fpm`.
5. Verify: booking counts unchanged, `payment_status` distribution matches the
   backfill rule, earnings widget total identical to the pre-deploy figure.

Rollback is the snapshot plus the previous tarball. With 10 rows, restore is
instant — another reason to do this now.
