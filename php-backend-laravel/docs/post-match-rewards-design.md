# Post-Match Reward Engine — audit & architecture

Status: **implemented 2026-09-19** on `fix/stabilization-security-stats` (uncommitted, undeployed). Decisions 1–5 approved as recommended. See “Implementation notes” at the end for what changed from this design and the deploy steps.

---

## Part 1 — Audit of what exists

### 1.1 Match completion

| Fact | Where | Consequence for rewards |
|---|---|---|
| One completion path: `MatchCompletion::finish()` → `followThrough()` runs **after the response** (stats → careers → ranks → ground → verification). | `app/Services/MatchCompletion.php` | The natural hook. Add a `rewards` step; it must never block the scorer's tap. |
| "Finished" = `live_matches.completed_at`, not `status`. | `LiveMatch::saving`, `scopeFinished()` | Trigger on the `completed_at` stamp, never on status strings. |
| Only the **scorer (creator)** calls `POST /matches/{id}/complete`; cricket also finishes itself when the chase ends (`MatchesController:908`); the web console is a third path. | `MatchesController::complete`, `LiveMatchController:231` | The other 10–20 players have **no completion moment on their phone**. Rewards must reach them by FCM + inbox + the match page, not only the scorer's screen. |
| A match can be **reopened** (winning ball undone) — `MatchCompletion::reopen()` clears `completed_at` and deletes player stats. | same | Anything granted at completion must be revocable until the result is final. |
| `followThrough()` is `dispatch(...)->afterResponse()` — synchronous in the same PHP worker, not queued. `QUEUE_CONNECTION=database`. | | Engine evaluation is fine there; FCM fan-out to many players should go on the queue (needs a running worker on prod — confirm). |

### 1.2 XP, trust, verification

| Fact | Where |
|---|---|
| XP formula `(base + win/MoM bonus) × trust × diversity`, all values admin-editable in /control → Platform rules → XP & trust. | `ActionboardXp`, `PlatformRules` (`xp.*`) |
| **XP is written at settlement, not completion.** Completion opens a 72h window; XP lands when both captains confirm (medium), an organiser verifies (high), a Haraan booking verifies (verified), or the window lapses (low, hourly `actionboard:expire-verifications`). | `MatchVerificationService::settle()` → `PlayerXpLedgerService::award()` |
| Ledger is idempotent — `award()` deletes and rewrites a match's rows, then `reaggregatePlayerXp()` **recomputes** `ranked_xp`/`casual_xp` from the ledger. | `PlayerXpLedgerService` |
| Private matches and guests (`id: null`) never earn XP. | same |
| Captain confirm / dispute are now properly gated (side membership, leaders, one dispute per pair). A dispute still does **not** revert settled XP. | `MatchesController::confirm/dispute` |

**Consequence:** at the moment the celebration plays, the player's XP for that match **does not exist yet**. Showing a hard "+45 XP" would be an invented number. The screen must show XP as *pending* with what unlocks it — which is also the best nudge we have to get results confirmed.

Any reward XP added to `users.casual_xp` directly would be **wiped** on the next `reaggregatePlayerXp()`. Reward XP needs its own ledger.

### 1.3 Badges & streaks

- Achievements are **computed on every read** in `PlayersController::buildAchievements()` — 10 hardcoded badges, mostly cricket (high score, wickets), from the ledger + career tables. No table, no `unlocked_at`, not admin-editable.
- The only "streak" is best **win** streak over settled ledger rows, ordered by `awarded_at` (settle time, not play time).
- `user_activity_days` records app-open days (heartbeat), not play.

**Consequence:** we can't celebrate "new badge unlocked" today — nothing knows when it was unlocked. Badges need persisting (keep the same keys so profiles don't change), and badge definitions must move into /control per the admin-controls-everything rule.

### 1.4 Coupons

- `coupons` supports fixed/percent, cap, min order, min tickets, per-customer limit, expiry, scope `event|venue|all`. Enforced in `BookingService::createOrder` and `validateCoupon`. Audited via `AuditsAdminChanges`.
- Codes are **shared** strings. There's no per-user issued code; `eligibility='phones'` is stored but **not enforced**.

**Consequence:** a reward coupon posted as a shared code would leak on WhatsApp in an hour. We need **owner-bound coupons** (`coupons.owner_user_id`, enforced at checkout).

### 1.5 Ads

- First-party banner system: `Ad` with a `PLACEMENTS` whitelist (`match_live`, `events`, `login_poster`), date windows, honest impression/click tracking (`AdTracker`, hashed viewer, de-dup windows), web `/go/ad/{id}`.
- `ads.hidden` member feature is enforced in `AppContentController::ads` and `PublicWebController`.
- **No mobile ad SDK** in the Android app (no AdMob/AppLovin), **no consent (UMP) flow**, no video creative support.
- **Gap:** `Ad` does **not** use `AuditsAdminChanges` and the Ads resource never calls `AdminAction::log` — ad edits in /control are currently unaudited. (Coupons and member plans are audited.)

### 1.6 Membership entitlements

- `MemberEntitlements` is the only gate (`allows`, `limit`, `authorize`, `assertWithinLimit`, atomic `consume` for monthly quotas, `assertChosen`). Plan values live in `member_plan_entitlements`, editable in /control; per-member `member_entitlement_overrides` with expiry.
- A test fails the build if any code compares plan codes.
- Adding a gate = add a `MemberFeature` key + catalogue migration + one enforcement point.

**Consequence:** the reward engine plugs in cleanly: new feature keys, zero plan-code checks. Overrides with expiry give us a free reward type — "7 days of Pro insights".

### 1.7 /control & audit

- `PlatformRules` = typed, ranged, admin-editable rules with `assertNotSecret()` refusing credential-looking keys; `ops.*` emergency switches.
- `AdminAction` is append-only and redacts secret-looking meta keys. `AuditsAdminChanges` logs field-level before→after for console users.
- Shield permissions per resource (`Action:Entity`).
- Secrets stay in `.env` (Razorpay, FCM, member webhook secret).

---

## Part 2 — Architecture

### 2.1 Principles

1. **Rewards never buy rank.** No reward (sponsored, ad-watched, or membership) touches `match_xp_ledger`, `ranked_xp`, or leaderboards. The anti-cheat spine stays intact.
2. **Anything worth money waits for a trusted result.** Coupons, sponsor codes and trials are granted *locked* at completion and *unlock* at settlement with trust ≥ an admin-set minimum. That stops fake self-scored matches farming Swiggy codes, and turns "confirm your result" into something players want to do.
3. **Nothing invented.** The screen shows only real grants. Pending XP is shown as pending, with the real rule that unlocks it.
4. **Server decides, client celebrates.** The app never computes eligibility or trusts "I watched the ad".
5. **Admin controls everything, secrets never.** Every program, rule, cap, copy and animation is in /control and audited. Sponsor API keys stay in `.env`; /control shows only "configured ✓/✗".
6. **Membership only through `MemberEntitlements`.**

### 2.2 Two-phase lifecycle

```
 match completed_at stamped ──► followThrough step "rewards"
                                   RewardEngine::evaluate(match, TRIGGER_COMPLETED)
                                   ├─ instant grants  (badges from match stats, play streak,
                                   │                   participation bonus XP, cosmetic)
                                   └─ locked grants   (coupons, sponsor codes, trials)
                                        status = pending_verification
                                   FCM + inbox to every registered participant

 settle() (confirm / organiser / venue / 72h expiry)
                                ──► RewardEngine::evaluate(match, TRIGGER_SETTLED)
                                   ├─ unlock locked grants if trust ≥ rewards.min_trust_for_value
                                   │   else expire them (reason shown to player)
                                   ├─ XP-based badges, win streak
                                   └─ FCM "Your rewards unlocked"

 reopen() (winning ball undone) ──► revoke unclaimed grants for that match
```

### 2.3 Engine (backend)

```
app/Services/Rewards/
  RewardEngine          evaluate(LiveMatch, trigger): participants → context → programs → rules → caps → issue
  RewardContext         user, match, side, won, mom, trust, player_match_stats row, EntitlementSet, streak
  Conditions/           registry of typed, whitelisted conditions (sport_in, match_type_in, min_trust,
                        won, is_mom, stat_at_least(key,n), streak_at_least, member_feature, org_in,
                        first_match_of_week…) — no free-form expressions, same idea as PlatformRules
  Issuers/              XpBonusIssuer, BadgeIssuer, HaraanCouponIssuer, CodePoolIssuer,
                        EntitlementTrialIssuer, OfferLinkIssuer
  RewardCaps            atomic conditional-UPDATE counters (same pattern as MemberEntitlements::consume)
  RewardedAdVerifier    AdMob SSV signature check
  RewardPresenter       API shape for the screen / wallet
```

- **Idempotent:** unique `(user_id, rule_id, match_id)` on grants; a re-run of `followThrough` or a second tap changes nothing.
- **Programs cached** like `AppSetting` (snapshot, busted on save).
- **Budget-safe:** program totals and per-user caps are checked and written in one conditional UPDATE, so two parallel completions can't overspend a sponsor's budget. Code pools are claimed with `UPDATE … WHERE grant_id IS NULL LIMIT 1` (atomic on every driver we use).
- **Fail-soft:** wrapped in `MatchCompletion::step()` — a reward failure is logged and never breaks stats or verification.
- **Kill switch:** `ops.rewards_disabled` (whole engine) + `ops.rewarded_ads_disabled`.

### 2.4 Data model (new tables)

| Table | Purpose / key columns |
|---|---|
| `reward_sponsors` | name, category (`payments|food|sports_brand|ott|other`), logo, brand colour, website, is_active, notes. **No credentials.** `integration_driver` (nullable string naming a config block in `config/rewards.php`, keys from `.env`). |
| `reward_programs` | kind (`haraan|sponsored`), sponsor_id, name, status (`draft|scheduled|live|paused|ended`), starts_at/ends_at, priority, targeting JSON (sports, match types, org ids, min trust), budget_total, budget_daily, grants_count, creative (card image, Lottie JSON, colours, headline, terms_url, disclosure text), requires_consent flag. |
| `reward_rules` | program_id, trigger (`match_completed|match_settled|rewarded_ad`), conditions JSON (validated against the registry), reward_type, reward payload JSON, lock_until_settled, per_user caps (day/week/program), unlock_method (`auto|rewarded_ad`), sort. |
| `reward_code_pools` / `reward_codes` | sponsor-supplied unique codes imported by CSV. `code` uses Laravel's `encrypted` cast; /control shows `••••1234`; reveal is an audited action. `grant_id` null until claimed. |
| `reward_grants` | the ledger: user_id, match_id, program_id, rule_id, type, status (`pending_verification|available|claimed|expired|revoked`), value snapshot JSON, coupon_id / code_id / override_id, unlock_reason, expires_at, claimed_at, revoked_reason. Unique (user_id, rule_id, match_id). |
| `reward_xp_ledger` | bonus XP entries (user, grant, amount, reason). Shown as **Bonus XP** on the profile — separate from ranked/casual XP, never in leaderboards. |
| `badge_definitions` | key (the existing 10 keys first), name, icon (vector glyph name), tier, sport (nullable), condition JSON, is_active, sort. |
| `player_badges` | user_id, badge_key, unlocked_at, match_id. `buildAchievements()` reads this instead of recomputing. |
| `player_streaks` | user_id, kind (`play_week`), current, best, last_period. **Weekly** play streak (played ≥1 settled/finished match in consecutive ISO weeks) — a daily streak doesn't fit amateur sport. |
| `rewarded_ad_sessions` | nonce (unique), user_id, grant_id, provider, status, started_at, verified_at, provider_txn_id (unique — replay guard). |
| `reward_screen_views` | user_id, match_id, seen_at — so the celebration plays once per match across devices. |
| `reward_events` | impression / claim / redeem / ad_complete, hashed viewer — sponsor reporting, same honesty rules as `AdTracker`. |

Existing tables touched:
- `coupons`: add `owner_user_id` (nullable), `source` (`manual|reward`), `reward_grant_id`. `BookingService` + `validateCoupon` reject an owned coupon for anyone else. Existing rows unaffected (null owner).
- No change to `match_xp_ledger`, `ActionboardXp`, or leaderboards.

### 2.5 Reward types

| Type | Issued as | Locked until settled? |
|---|---|---|
| Bonus XP | `reward_xp_ledger` row | no (low value, not ranked) |
| Badge | `player_badges` row | badges from match stats: no; XP/win based: fires on settle |
| Streak bonus | Bonus XP + streak badge | no |
| Haraan coupon | new owner-bound `coupons` row (random code, scope event/venue/all, expiry) | **yes** |
| Sponsored code | claim from `reward_codes` pool | **yes** |
| Sponsored offer link | tracked link, no code (brand landing page) | admin choice |
| Membership trial | `member_entitlement_overrides` rows with `ends_at` | **yes** |

### 2.6 Membership integration

New `MemberFeature` keys (values per plan in /control; no plan codes in code):

| Key | Type | Meaning | Suggested defaults (Free / Pro / Hero) |
|---|---|---|---|
| `rewards.ad_unlock_skip` | boolean | Rewards normally unlocked by watching an ad unlock without one. Also the only honest behaviour for members with `ads.hidden`. | off / on / on |
| `rewards.member_programs` | boolean | Access to programs marked members-only. | off / on / on |
| `rewards.offers_per_match` | limit | Max sponsored offers shown on one reward screen. | 1 / 2 / 3 |
| `rewards.bonus_xp_multiplier_pct` | limit | Bonus-XP boost (%). Bonus XP only — never ranked. | 0 / 10 / 25 |

Rules:
- Any member whose plan has `ads.hidden` is **never shown a rewarded ad**. The engine checks `ads.hidden` first; if an ad-unlock rule applies they get it via `rewards.ad_unlock_skip` or not at all.
- A rule can require a feature (`member_feature` condition) — evaluated through `MemberEntitlements::allows()`.
- Trial rewards write overrides; `MemberEntitlements::flush($userId)` afterwards.

### 2.7 Rewarded ads

Today there's no ad SDK in the app, and first-party banners can't prove a video was watched. Recommendation:

- **Google AdMob Rewarded + server-side verification (SSV).** Flow: app `POST /api/rewards/ad-sessions {grant_id}` → server returns nonce as `custom_data` → AdMob plays → Google calls `GET /api/rewards/admob/ssv` → we verify Google's ECDSA signature against their published public keys (**no shared secret to store**), match nonce, check `transaction_id` not seen → grant becomes `available` → app polls/receives socket event. The client's "I finished" callback is never trusted.
- Needs: Google UMP consent flow (DPDP Act / EEA), ad-unit IDs in /control (IDs aren't secrets), daily cap per user (`rewards.ad_daily_cap`), "Watch a 15s video to unlock ₹50 off" shown **before** the video, never auto-play, skip always available.
- First-party sponsor videos (extend `Ad` with a video creative) are possible later but can only unlock low-value rewards, because completion can't be verified.

### 2.8 API

| Endpoint | Notes |
|---|---|
| `GET /api/matches/{id}/rewards` | viewer's reward screen: celebration config, grants (instant + locked with unlock rule), pending XP card (real settlement state + what unlocks it), offers, ad-unlock offers. If evaluation hasn't run yet (afterResponse race), evaluates idempotently inline. 404 for non-participants. |
| `POST /api/matches/{id}/rewards/seen` | marks the celebration played. |
| `POST /api/rewards/grants/{id}/claim` | reveals code / applies coupon; owner-only; rate-limited. |
| `GET /api/rewards/wallet` | all grants, filter by status; expiring soon first. |
| `POST /api/rewards/ad-sessions` / `GET /api/rewards/ad-sessions/{nonce}` | start/poll rewarded ad. |
| `GET /api/rewards/admob/ssv` | public callback, signature-verified, throttled. |
| `POST /api/rewards/events` | impression/click beacons for sponsor reporting. |

Realtime: reuse Reverb (`reward.granted` on the user channel) + FCM data message deep-linking to `haraan://match/{id}/rewards`; also an inbox notification.

### 2.9 Android

- `ui/rewards/PostMatchRewardsScreen.kt` + `RewardsViewModel`, `data/RewardsRepository.kt`.
- **Entry points:** scorer → right after `completeMatch` succeeds; every other participant → FCM deep link or a "Your rewards" row on the finished match's detail page; plays once per match (server `seen_at`).
- **Celebration (≈1.8s, skippable):** Lottie from /control (already bundled: `lottie-compose 6.7.1`) with a Compose-canvas fallback; win/draw/loss variants; haptics (VIBRATE permission — see the haptics landmine note).
- **Reward sheet order:** result + pending/settled XP card (with "Ask your captain to confirm" CTA when pending) → badges → streak → Haraan coupons → sponsored offers (clearly labelled "Sponsored") → watch-to-unlock cards.
- Follows house rules: blue buttons only, vector glyphs not emoji, no boxed stat grids, no invented numbers.
- Web parity (the website's match page) comes after the app.

### 2.10 /control

New **Rewards** cluster (Shield permissions per resource; `Publish:RewardProgram` needed to set a program live):

- **Programs** — create/schedule/pause, targeting, budget, creative upload (image, Lottie JSON validated like SectionTheme), preview of the reward card.
- **Rules** (relation manager on Program) — trigger, condition builder (typed from the registry), reward payload, caps, lock-until-settled, unlock method.
- **Sponsors** — profile + category; integration shows only "configured ✓/✗".
- **Code pools** — CSV import (audited, count only, never the codes), masked list, audited "reveal" action.
- **Badges** — definitions; the existing 10 seeded with the same keys.
- **Grants** — read-only ledger with filters; "Revoke" action (reason required, audited).
- **Rewards analytics** — per program: grants, claims, redemptions, ad completions, budget burn; export for sponsors.
- **Platform rules → new `rewards` section:** engine on/off, min trust for valuable rewards (default `medium`), grant expiry days, max cards per screen, celebration on/off, ad daily cap, AdMob unit IDs. `ops.rewards_disabled` and `ops.rewarded_ads_disabled` under Emergency switches.

**Audit:** every new model uses `AuditsAdminChanges`; explicit `AdminAction::log` for publish/pause, code import, code reveal, grant revoke, budget change. Codes and sponsor keys are never written to meta (the code column name is added to redaction).

---

## Part 3 — Risks & compliance

- **Pay-to-win:** prevented by §2.1.1 — worth a test that scans for writes to `match_xp_ledger` outside `PlayerXpLedgerService`.
- **Farming:** valuable rewards need settled trust ≥ medium, the existing diversity decay already makes repeat-opponent matches cheap, plus per-user caps and program budgets.
- **Chance-based rewards (scratch cards):** keep rewards **deterministic** by default. Random prize mechanics in India vary by state, so get legal review before adding them.
- **Sponsored disclosure:** every sponsored card labelled "Sponsored" + sponsor name + terms link (ASCI guidelines).
- **Play policy:** AdMob rewarded is allowed when it's opt-in and the reward is disclosed first. Membership trials granted as rewards are fine; *selling* membership in-app stays web-only (existing decision).
- **Privacy:** sponsor reporting is aggregate only; no user identity shared with sponsors unless a program explicitly asks for consent.

---

## Part 4 — Delivery phases

1. **Core engine + Haraan rewards** — tables, engine, completion/settle/reopen hooks, persisted badges + weekly streak, bonus XP, reward screen, wallet, /control Programs/Rules/Badges/Grants, rules section, kill switch.
2. **Owner-bound Haraan coupons** — coupon changes + checkout enforcement.
3. **Sponsored programs** — sponsors, code pools, analytics/export, member-only programs.
4. **Rewarded ads** — AdMob SDK + UMP consent + SSV, behind a feature flag.
5. **Web parity.**

Fix alongside phase 1 (found in this audit): add `AuditsAdminChanges` to `Ad` so ad edits are audited.

## Decisions needed

1. Bonus XP as a separate, never-ranked currency (recommended) — or no reward XP at all?
2. Valuable rewards locked until the result is settled (recommended) — or granted at completion?
3. Rewarded ads via AdMob SSV (recommended) — or first-party sponsor videos only?
4. Membership perk shape in §2.6 — keep, trim, or change?
5. Weekly play streak (recommended) vs daily.


---

## Implementation notes (2026-09-19)

Deviations from the design, each for a concrete reason:

- **Outcome comes from the scoreline, not `live_matches.result`.** No scoring path writes `result` (only a seeder does) — which also means the existing competitive-XP *win bonus never fires*. Rewards use `MatchPlayerStatsCalculator::resultFor()`. Competitive XP was deliberately left unchanged. `mom_player_id` is likewise never written, so “player of the match” conditions can’t fire until POTM is persisted.
- **A low-trust settlement leaves money rewards locked (reason `trust_too_low`), not expired**, so an organiser or venue raising trust later still unlocks them; they expire on their date otherwise.
- **The AdMob callback lives at `/api/webhooks/admob/rewarded`** so maintenance mode (which exempts `api/webhooks`) never drops Google’s verifications.
- **Reward notices use the existing inbox** (`notifications`, audience `user`, new `source = rewards`) so push fan-out is the existing `SendNotificationPush` job; the composer list filters them out.
- **Reward coupons are single-use by `max_uses = 1` plus an in-flight check**, not `per_customer_limit`: the per-customer count includes expired checkouts, so an abandoned checkout would have burned the reward.

Deploy steps: `php artisan migrate` → `php artisan rewards:backfill-badges` (before the first match finishes) → scheduler already runs `rewards:maintain` every 15 min → queue worker for pushes → configure the AdMob SSV callback URL `https://haraan.app/api/webhooks/admob/rewarded` in the AdMob console → set `rewards.admob_ad_unit_id` and turn on `rewards.rewarded_ads_enabled` in /control → set `ADMOB_APP_ID` in `android-app/gradle.properties` for release builds (defaults to Google’s test app ID) → new APK.
