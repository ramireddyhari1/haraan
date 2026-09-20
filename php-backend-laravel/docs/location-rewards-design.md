# Location-Based Targeted Rewards — audit & architecture

Status: **Phase 1 implemented 2026-09-20**, uncommitted and undeployed, shipping dark behind
`rewards.geo_enabled = false`. Extends the post-match reward engine
(`docs/post-match-rewards-design.md`, built 2026-09-19, also still undeployed) with a geography
dimension. No existing reward table, state machine or invariant is modified. See
“Implementation notes” at the end for what was built and what changed from this design.

---

## Part 1 — Audit of what exists

### 1.1 The reward engine (built, undeployed)

| Fact | Where |
|---|---|
| Three idempotent entry points: `onCompleted` / `onSettled` / `onReopened`. | `Services/Rewards/RewardEngine.php` |
| **`RewardLedger` is the only writer to `reward_grants`.** Dedupe by unique `dedupe_key` = `rule:{rule}:match:{match}:user:{user}`; budgets spent by conditional `UPDATE … WHERE grants_count < budget`; a sponsor code taken by `UPDATE … WHERE grant_id IS NULL`. | `Services/Rewards/RewardLedger.php` |
| Commercial unit = `reward_programs` (sponsor, status, dates, priority, budget_total/daily). Firing unit = `reward_rules` (trigger, conditions, reward_type, payload, caps, expiry). | `create_reward_tables` migration |
| Grant states: `locked → available → claimed → redeemed`, plus `expired` / `revoked`. Every transition is a conditional UPDATE on the state it expects to leave. | same |
| Claiming issues the actual value: owner-bound coupon, revealed sponsor code, or time-boxed member overrides. | `Services/Rewards/RewardClaims.php` |

### 1.2 Targeting available today

`reward_programs` filters on **`sports`**, **`match_types`**, **`members_only`**.
`reward_rules.conditions` is a closed, validated list — `result`, `player_of_match`,
`min_registered_per_side`, `min_play_streak_weeks`, `min_trust`, `member_feature`
(`Support/Rewards/RewardConditions.php`; conditions are never evaluated as an expression).

**There is no location dimension anywhere in the reward engine.** A sponsor can buy "cricket,
members only, winners" nationally and nothing narrower.

### 1.3 Location data that already exists

| Source | Columns | Quality |
|---|---|---|
| `live_matches` | `latitude` / `longitude` (decimal 10,7, index `live_matches_geo_idx`), `locality`, `district`, `state`, `ground_id` | **A public match cannot be created without a GPS fix** — `Match/StoreMatchRequest.php:71` makes lat/lng required unless `isPrivate`. Private and pre-2026-07-21 matches have names only. |
| `match_grounds` | `place_id`, `name_key`, lat/lng, `locality`, `district` | The canonical ground a free-text venue string resolves to. |
| `venues`, `events` | lat/lng, `location` / area | Partner-owned, admin-curated, Places-backed. |
| `users` | `district`, `state` (free text), `privacy_show_district` | **No coordinates. No verified location. Ever.** |
| `MatchProximity` | haversine + name-tier buckets; radii admin-editable (`creation.proximity_*_km`) | The existing, proven distance primitive. Ranking is done in PHP over a bounded candidate set, not in SQL. |

The viewer's position reaches the server only as **`?lat` / `?lng` query params on feed reads**
(`LiveMatchController::viewerPosition`). It is client-supplied and unverified, and today it can
only affect sort order.

### 1.4 Anti-cheat invariants that must survive

1. **Money-value rewards always start locked.** `RewardTypes::MONEY_VALUE` (coupon, sponsor code,
   membership trial) is not configurable; those grants unlock only when the match settles at
   `rewards.min_trust_to_unlock` or better (`RewardEngine::onSettled`).
2. **Reward XP never touches competitive XP.** Bonus XP lives in `bonus_xp_ledger`; `match_xp_ledger`,
   `users.ranked_xp` / `casual_xp` and every leaderboard are untouched — and would be wiped by
   `reaggregatePlayerXp()` anyway.
3. **Private matches earn nothing** unless `rewards.private_matches` is on.
4. **A reopened match revokes its unclaimed grants** and reverses their Bonus XP.
5. **Caps compose:** program `budget_total`/`budget_daily`, `rewards.max_grants_per_match`,
   `rewards.daily_grant_cap`, per-rule `per_user_daily_cap` / `per_user_total_cap`.
6. **Guests earn nothing** — squad entries with `id: null` are skipped.

### 1.5 Coupon ownership rules that must survive

A reward coupon is created by `RewardClaims::issueCoupon()` with `owner_user_id = claimer`,
`source = 'reward'`, `reward_grant_id`, `max_uses = 1`, a random code, and its own expiry.
`Coupon::usableBy()` (`Coupon.php:152`) and `BookingService.php:450` enforce that only the owner
can spend it and that spending it is terminal. Shared-code coupons keep `owner_user_id = null`
and behave exactly as before.

### 1.6 Admin & staff roles

- `/control` Rewards resources gate on `canManage('marketing')` (`RewardProgramResource::canAccess`).
  Partners are lane-locked by `partner_type` and **barred from `/control` entirely**
  (`User::canAccessPanel`).
- Platform rules carry a per-section `workspace`; the `rewards` section is `marketing`.
  Emergency switches (`ops.rewards_disabled`, `ops.rewarded_ads_disabled`) are super-admin only.
- Reward programs are audited field by field via `AuditsAdminChanges`. (The earlier audit caught
  `Ad` *not* doing this — a new resource must not repeat that.)

---

## Part 2 — Proposed architecture

### 2.0 The one decision everything follows from

> **The match's GPS fix is what gets targeted. The player's phone is not.**

A player's reported position is spoofable in thirty seconds, and a spoofable input that decides
who receives something worth money is a fraud vector, not a feature. A match's fix is already
mandatory for public creation, already part of a record the trust/verification pipeline governs,
and already public in the feed. Targeting it means **geo introduces no new fraud surface** — the
only way to farm a zone is to fake a match, which the existing trust gate already answers.

The corollary, stated as a rule the code enforces:

> **Client-supplied location may sort. Only match location may grant.**

### 2.1 Schema (additive only)

```
reward_zones                      -- named, reusable targeting geometry (audited)
  id, name, kind                  -- circle | admin_area
  anchor_type, anchor_id          -- point | venue | ground | event  (a venue zone tracks the venue's own coords)
  latitude, longitude, radius_m   -- circle
  locality, district, state       -- admin_area
  place_id, is_active, notes, timestamps

reward_program_zones              -- program_id, zone_id, mode (include|exclude)
reward_rule_zones                 -- rule_id,    zone_id, mode   (empty = inherit the program's)
```

On `reward_grants`, two additive columns: **`zone_id`** (nullable, indexed with `created_at` for
the per-zone report) and, inside the existing `value` JSON snapshot,
`geo = {zone, zone_name, distance_km, source}` where `source ∈ match_gps | ground | name`.
Every grant then answers "why did this player get this?" forever, without a join to mutable config.

No column on any existing reward table changes type or meaning. A program with no zones targets
everywhere — which is exactly today's behaviour, so the migration is a no-op for live config.

### 2.2 Resolution — `Support\Rewards\RewardGeo`

One new support class, pure and testable, called by `RewardEngine` *before* it calls the ledger:

1. **Point for the match:** `live_matches.lat/lng` → else `match_grounds` via `ground_id` → else none.
2. **Containment:** circle zones by haversine (reuse the `MatchProximity` formula — do not write a
   second one); `admin_area` zones by the same normalised name comparison `MatchProximity::matches`
   already uses, against `locality` / `district` / `state`.
3. **No point and no name match → the program does not fire.** With
   `rewards.geo_require_coordinates = true` (the default), a name-only match never satisfies a
   zone at all.
4. Returns the **tightest** matching include-zone and its distance, or null when an exclude-zone hits.

Cost is negligible: zones are a small admin-authored table, cached like `PlatformRules`, and the
engine already runs once per match, per player, after the response.

### 2.3 Selection when several programs match

Today programs are ordered by `priority`. Add one tiebreak: **the tighter zone wins** (smallest
matched radius first, zone-targeted before anywhere-targeted), behind
`rewards.geo_tighter_zone_wins` (default on). A 3 km turf offer should beat a national one on the
same match. This changes **ordering only** — not eligibility, not caps, not any grant semantics.

### 2.4 Budgets per geography — recommendation

A sponsor wanting "₹50k in Kadapa, ₹50k in Vizag" should create **one program per city**. Programs
are cheap, each already owns `budget_total`, `budget_daily` and an atomic spend. A
`reward_zone_budgets` table would duplicate machinery that already works.

Revisit only if a single sponsor routinely needs more than ~10 cities; the escape hatch is the same
conditional-UPDATE counter pattern keyed on `(program_id, zone_id)`.

### 2.5 The perks vault — integration, not a new surface

The vault is `PerksVaultSection` in `ui/rewards/RewardTicket.kt`, fed by `GET /api/rewards`
through `RewardPresenter::grant()` / `wallet()`. Location is carried as **snapshot data on grants
that already exist**:

- `RewardPresenter::grant()` emits `geo` from `value.geo`. The vault card gains a
  `2.1 km · Kadapa City` chip and, when the zone has an anchor, a **Directions** action.
- Rewards with a physical redemption point get `redeem_at` (the anchor's name + coords) inside the
  value snapshot. **No new `RewardTypes` member** — `sponsor_code` and `offer_link` already cover
  redeem-in-store, and the type list stays closed.
- `GET /api/rewards?near=lat,lng` **reorders** the vault by distance to each grant's redeem anchor.
  This is the one place client coordinates are accepted, and they touch presentation only.
- One payload change: `RewardTypes::normalizePayload(HARAAN_COUPON)` accepts `venue_id` when
  `scope = 'venue'`. That column already exists on `coupons` and is already enforced at checkout —
  it is how "₹100 off, at this turf only" is expressed without inventing anything.

No new screen, no new nav entry, no second wallet.

### 2.6 /control surfaces (admin-controls-everything)

- **Rewards → Zones**: new Filament resource, `canAccess → canManage('marketing')` to match the
  other reward resources, `AuditsAdminChanges` on the model, map/Places picker reused from
  `VenueForm` / `EventForm`.
- **Program form → "Where"**: include-zone and exclude-zone multi-selects, defaulting to *Anywhere*.
- **Rule form → "Where"**: optional zone subset, for tiering inside one program.
- **Per-zone widget**: grants, budget burn, claim and redemption rate by zone.
- New platform rules, all in the existing `rewards` section (workspace `marketing`):

| Key | Default | Why |
|---|---|---|
| `rewards.geo_enabled` | `false` | Ships dark. Off = every program fires exactly as today. |
| `rewards.geo_require_coordinates` | `true` | A name-only match never satisfies a zone. |
| `rewards.geo_max_zone_radius_km` | `50` | Stops a "local" zone being drawn at 2000 km. |
| `rewards.geo_default_radius_km` | `5` | Sensible new-zone default. |
| `rewards.geo_tighter_zone_wins` | `true` | Ordering rule in §2.3. |
| `rewards.geo_min_trust_for_zone_money` | `high` | §2.7. |
| `rewards.geo_ground_grants_per_day` | `25` | §2.7 velocity guard. |

### 2.7 Threat model — what geo adds, and the answer to each

| Threat | Answer |
|---|---|
| **Fake match inside a lucrative zone.** | Unchanged: money-value grants start locked and need settlement trust. Location never unlocks anything. Geo adds no bypass. |
| **GPS spoofing at match creation, now monetised.** | New rule `rewards.geo_min_trust_for_zone_money` (default `high`): money-value rewards from a *zone-targeted* program need organiser-verified or venue-verified trust, one notch above the global floor. Plus the grant records `source`, so a spoofing pattern is visible per ground. |
| **A group farming one turf all weekend.** | New `rewards.geo_ground_grants_per_day`, a counter on `(ground_id, day)` spent with the same conditional-UPDATE pattern as program budgets. Without it, one group at one ground can drain a sponsor's city budget. |
| **One player farming a zone.** | Already covered: a zone program owns its own rules, so the existing per-rule `per_user_daily_cap` / `per_user_total_cap` *are* per-zone caps. No new column. |
| **Privacy.** | No player location is ever stored. The grant stores the *match's* coordinates, which are already public in the feed. Private matches stay excluded, so a home-address fix from a private game never reaches a sponsor's zone report. `value.geo` is shown to the grant's owner only, like the rest of the snapshot. |

### 2.8 Explicitly unchanged

`RewardLedger` remains the only writer — `RewardGeo` resolves and hands data in. `dedupe_key`
keeps its format, so re-resolving zones can never produce a second grant. `MONEY_VALUE` is
untouched. `bonus_xp_ledger` is untouched. Coupon issuance keeps `owner_user_id` + `max_uses = 1`;
a zone never widens ownership. Caps run after geo filtering, in the same order. `onReopened`
revocation needs no change, because geo adds no state to the machine.

### 2.9 Rollout

1. Deploy the **post-match reward engine first** — it is still undeployed, and stacking a second
   undeployed layer on it doubles the blast radius of one migration. (Migrate + `rewards:backfill-badges`.)
2. Ship geo dark: migration + resource + rules, `rewards.geo_enabled = false`. Zero behaviour change.
3. Author zones and one pilot program in /control. Turn `geo_enabled` on. Watch the per-zone widget.
4. Reverse by flipping one switch: with geo off, an empty zone set means "anywhere", i.e. today.

### 2.10 Tests to add

Zone containment and the exclude path; name fallback under both settings of
`geo_require_coordinates`; tighter-zone-wins ordering; **a money-value grant in-zone still starts
locked**; a spoofed `?near=` changes order but never eligibility or value; `dedupe_key` stable
across re-resolution; ground/day and program budget counters under concurrent completion.

---

## Open questions

1. **Zones on rules as well as programs** (§2.1) — needed for in-program tiering, or is
   one-program-per-tier enough for v1?
2. **`geo_min_trust_for_zone_money = high`** (§2.7) — correct, or too strict for launch given how
   few gully matches reach organiser-verified today?
3. **Per-city programs vs. zone budgets** (§2.4) — confirm the per-program recommendation.

Phase 1 was built on the recommended answers: rule-level zones **are** included (they were cheap
once the pivot existed, and cost nothing when unused), the trust floor defaults to `high` and is
admin-editable, and there are no zone budget tables.

---

## Implementation notes — Phase 1 (2026-09-20)

### What was built

| Area | Files |
|---|---|
| Schema | `2026_09_20_100002_create_reward_zone_tables` — `reward_zones`, `reward_program_zones`, `reward_rule_zones`, `reward_ground_days`, `reward_grants.zone_id` |
| Model | `RewardZone` (audited); `zones()` / `includeZones()` / `excludeZones()` on `RewardProgram` and `RewardRule`; `RewardGrant::zone()` and `requiredTrustLevel()` |
| Resolver | `Support\Rewards\RewardGeo` + `Support\Rewards\ZoneMatch` |
| Engine | `RewardEngine::targets()`, geo-aware `applyRules()`, per-grant trust floor in `onSettled()` |
| Ledger | `grantFromRule()` takes a `ZoneMatch`, writes `zone_id` + `value.geo`, spends `spendGroundDay()` |
| API | `geo` on every grant; `GET /api/rewards?near=lat,lng` reorders by distance |
| /control | `RewardZoneResource` + “Where” on the program form and “Only where” on the rule form; 7 new `rewards.geo_*` platform rules |
| App | `RewardGeo` / `RewardPlace` models, `RewardLocationLine` on the vault card with a Directions tap |
| Tests | `tests/Feature/Rewards/RewardZoneTest.php` — 11 tests, 26 assertions |

### Changed from the design

- **`ActionboardXp::higherTrust()`** was added so the zone floor can never sit *below* the
  platform-wide floor, whichever way an admin sets the two. The design assumed the zone value
  simply replaced it; taking the stricter of the two is the safe reading.
- **`reward_ground_days.ground_key` is a string, not an FK** to `match_grounds`. Most gully
  grounds have no canonical row, so the key falls back to coordinates rounded to ~110 m. An FK
  would have left exactly the matches most worth rate-limiting uncounted.
- **An area zone reports no distance.** An administrative area has no centre worth measuring to,
  and a synthesised one would have put a fabricated “12 km away” on a player's card.
- **An area zone with no names set matches nothing.** Treating an empty area as “everywhere”
  would turn a half-finished config into a national campaign.
- **The coupon payload's `venue_id` key is omitted when unset** rather than stored as null, so no
  existing rule's payload JSON changes shape when it is next saved.

### Verification

- Full suite: **1055 of 1064 passed**, 4055 assertions.
- `tests/Feature/Rewards` 66 passed; `tests/Feature/Membership` passed (191 together).
- `:app:compileDebugKotlin` — BUILD SUCCESSFUL.
- Pre-existing, unrelated: every one of the 8 failures is `GameHubEnterpriseOperationsTest`
  (Livewire/Blade `&` vs `&amp;` assertions plus one null relation). It is order-dependent —
  3 failures in one full run, 8 in another, and a steady 7 failed + 1 error when run alone,
  **identical before and after this work**. Its three source files are clean at HEAD and
  untouched here.

### Deploy

1. Deploy the **post-match reward engine first** (§2.9) — migrate + `rewards:backfill-badges`.
2. Then migrate this. Nothing changes: `rewards.geo_enabled` defaults to false.
3. Author zones in /control → Rewards → Zones, attach them to one pilot program, then turn
   `rewards.geo_enabled` on.
4. Rollback is the same switch. With it off, every program reaches every location again.
