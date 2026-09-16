# Member subscriptions — Phase 1 audit & design

Status: implemented locally on `feat/partner-branches-cafe-lane`, **not deployed**.
Scope: member (player / fan) plans Free · Pro · Hero. Partner billing is out of scope and untouched.

---

## 1. Audit — what existed before this work

| Area | Finding | Consequence for this design |
|---|---|---|
| Partner billing | `partner_plans`, `partner_subscriptions`, `partner_credits`, `credit_packs`; `PlanEntitlements` (partner automations quota); `RazorpayBilling` (subscription + credit + ticket webhooks); `POST /api/webhooks/razorpay`. | **Do not touch.** Member billing gets its own tables, service, entitlement engine and webhook endpoint. Nothing in the partner path is edited. |
| Partner webhook | `RazorpayBilling::applyWebhook` looks subscriptions up by `external_id` in `partner_subscriptions` only; unknown ids return `unknown_subscription`. | A member subscription event hitting the partner endpoint is a harmless no-op, and vice-versa. The member endpoint only ever reads `member_subscriptions`. |
| Razorpay plumbing | REST (no SDK; packagist unreachable), `RazorpayGateway` for orders/links/signatures, keys in `config/services.razorpay`. | Member client is a separate `MemberRazorpayClient` (subscriptions/plans API) reusing the same key/secret config. Separate webhook secret `RAZORPAY_MEMBER_WEBHOOK_SECRET`. |
| Member entitlements | **None.** No plan concept for members; every member feature is free and ungated. No `is_pro` flags anywhere. | Clean slate — the gate is introduced once, centrally, with no legacy checks to migrate. |
| Costly member features | AI delivery review (`MatchDeviceController::reviewClip`, a Vertex video call per clip); AI career read (`PlayersController::careerAnalysis`, Gemini); tournament hosting; camera angle pairing; ads on `/api/ads`. | These are the real, server-enforceable gate points used by the matrix below. |
| Android payments | Razorpay Checkout SDK 1.6.40; `MainActivity` implements `PaymentResultWithDataListener`; `PaymentBridge` one-shot handler; `HaraanHttp` Retrofit client for new endpoints. | Subscriptions use the same SDK with `subscription_id`; `PaymentBridge.Success` gains `subscriptionId`. New repository goes through `HaraanHttp`. |
| Auth | `auth.jwt` sets `auth_user`; `auth.jwt.optional` for guest-serving routes. `/control` access via `User::canManage()`. | Membership APIs are `auth.jwt`; catalogue is `auth.jwt.optional`. Plans = `canManage('admin')`, subscriptions = `canManage('finance')`. |

---

## 2. Feature entitlement matrix

All values live in the database (`member_plan_entitlements`) and are editable in `/control` without a deploy. The keys and their *types* are fixed in code (`App\Support\Membership\MemberFeature`), because code enforces them.

| Key | Type | Free | Pro | Hero | Enforced at |
|---|---|---|---|---|---|
| `ads.hidden` | boolean | — | ✓ | ✓ | `AppContentController::ads` returns no ads |
| `ai.career_read` | boolean | — | ✓ | ✓ | `PlayersController::careerAnalysis` (profile owner's plan; also stops the Gemini spend) |
| `ai.delivery_review` | monthly quota | 2 | 20 | unlimited | `MatchDeviceController::reviewClip` — consumed only when a NEW review starts (cached / in-flight reviews are free) |
| `tournaments.active_hosted` | concurrent limit | 1 | 3 | unlimited | `TournamentsController::store` — counts the host's tournaments with `end_date >= today` |
| `matches.camera_angles` | per-match limit | 1 | 2 | 2 | `MatchDeviceController::store` — live (pending/connected) roles on that match |
| `profile.member_badge` | boolean | — | ✓ | ✓ | `membership.badge` on the player payload |
| `support.priority` | boolean | — | — | ✓ | Support thread payload + `/control` Support list marker |

Regressions to call out: before this work AI career read, unlimited delivery reviews, unlimited tournaments and both camera angles were free for everyone. The seeded Free values make them paid. Every one is a single toggle/number in `/control` if the business wants to grandfather.

---

## 3. Database schema

```
member_plans                       member_features (catalogue; key/type owned by code)
  id, code UNIQUE (free|pro|hero)    id, key UNIQUE, name, description,
  name, tagline, description         type (boolean|limit|quota), unit,
  rank (0 free < pro < hero)         sort, is_visible
  is_default, is_active, sort
  timestamps                       member_plan_entitlements
                                     id, plan_id FK, feature_key,
member_plan_prices                   enabled bool, limit_value int NULL (NULL = unlimited)
  id, plan_id FK                     UNIQUE(plan_id, feature_key)
  interval (month|year)
  amount_paise, currency           member_entitlement_overrides (support comps)
  razorpay_plan_id UNIQUE NULL       id, user_id FK, feature_key, enabled,
  is_active, timestamps              limit_value NULL, expires_at NULL,
  (amount/interval immutable once    reason, granted_by FK NULL, timestamps
   a razorpay_plan_id exists)
                                   member_usage_counters
member_subscriptions                 id, user_id FK, feature_key,
  id, user_id FK, plan_id FK,        period_start date, used int,
  price_id FK NULL                   UNIQUE(user_id, feature_key, period_start)
  provider (razorpay|admin)
  status                           member_payments
  provider_subscription_id UNIQUE    id, subscription_id FK, user_id FK,
  current_period_start/end           provider_payment_id UNIQUE, provider_invoice_id,
  cancel_at_period_end bool          amount_paise, currency, status, method, paid_at
  cancelled_at, ended_at
  replaces_subscription_id NULL    member_subscription_events (audit + idempotency)
  change_type (new|upgrade|          id, subscription_id NULL, user_id,
    downgrade|interval)              type, provider_event_id UNIQUE NULL,
  paid_count, last_event_at          from_status, to_status, actor_id NULL,
  checkout_expires_at, notes json    payload json, created_at
  timestamps
  INDEX(user_id, status)
```

The Free/Pro/Hero rows, features and default entitlements are inserted by a **data migration**, so `migrate` on deploy leaves a working catalogue. Paid prices ship `is_active = false` with no Razorpay plan id — nothing is purchasable until an admin creates the Razorpay plan from `/control`.

---

## 4. Entitlement engine (centralised)

One class answers every question: `App\Services\Membership\MemberEntitlements`.

```
$ent->for(?User)                       → EntitlementSet (plan, source subscription, resolved values)
$ent->allows(?User, key)               → bool
$ent->limit(?User, key)                → ?int   (null = unlimited)
$ent->authorize(?User, key)            → void | throws EntitlementDenied
$ent->assertWithinLimit(User, key, $currentCount)
$ent->consume(User, key, $n = 1)       → atomic quota consumption (row lock), throws when exhausted
$ent->usage(User, key)                 → used this period
```

Resolution order: guest/no subscription → default plan (`is_default`). Otherwise the highest-`rank` subscription that `grantsAccess()` now. Then unexpired `member_entitlement_overrides` for the user replace the plan value for that key. Results are memoised per request and flushed on any subscription write.

`EntitlementDenied` renders one JSON shape everywhere (HTTP 403):

```json
{ "error": "Delivery reviews are part of Pro.", "code": "upgrade_required" | "limit_reached",
  "feature": "ai.delivery_review", "limit": 2, "used": 2, "plan": "free", "upgrade_plan": "pro" }
```

Route-level boolean gates use middleware `member.entitled:<key>`. Call sites never compare plan codes — `grep -rn "'pro'\|'hero'" app/Http` stays empty by test.

---

## 5. Subscription lifecycle

Local status mirrors Razorpay exactly, plus `abandoned` (checkout dismissed) for provider `razorpay`, and `active|expired|revoked` for provider `admin`.

```
            subscribe                 mandate ok         first charge
  (none) ─────────────► created ─────────► authenticated ─────────► active ◄──┐
                          │  dismissed / expire_by                     │  charged (renewal)
                          ▼                                            ├──────────┘
                      abandoned                         charge failed  ▼
                                                                    pending ──retries ok──► active
                                                                       │ retries exhausted
                                                                       ▼
                                                                    halted ──(customer fixes)──► active
  active ──cancel(now)──► cancelled
  active ──cancel(at cycle end)──► active + cancel_at_period_end ──cycle end──► cancelled
  active ──total_count used──► completed          created/authenticated ──► expired
```

Access (`MemberSubscription::grantsAccess`):

| Status | Grants plan entitlements? |
|---|---|
| active | yes, until `current_period_end + grace` (default 48h) — covers a late renewal webhook |
| active + cancel_at_period_end | yes, until `current_period_end` (no grace) |
| pending | yes, until `current_period_end + grace` — Razorpay is still retrying |
| halted, cancelled, completed, expired, created, authenticated, abandoned, revoked | no |
| admin `active` | yes, until `current_period_end` (NULL = indefinite) |

Webhook correctness rules:
- **Idempotent**: `X-Razorpay-Event-Id` stored UNIQUE; payments keyed on `provider_payment_id` UNIQUE.
- **Ordered**: every event carries `created_at`; a status change older than `last_event_at` is ignored (payments are still recorded).
- **Authoritative periods**: `current_start`/`current_end` come from Razorpay's entity, never computed locally.
- **Membership-only**: lookups hit `member_subscriptions` by `provider_subscription_id`; unknown ids (partner subscriptions) are ignored.

Plan changes:
- **Upgrade** (higher rank): new subscription created and checked out immediately; when it activates, the old one is cancelled at Razorpay *immediately* and marked `cancelled` (change_type `upgrade`). Both overlap for seconds at most; the resolver always picks the higher rank.
- **Downgrade / interval change**: new subscription created with `start_at = old current_period_end`; once the customer authenticates it, the old one is cancelled *at cycle end*. The user keeps what they paid for; the new plan takes over when it begins charging.
- Only one change may be in flight; a second subscribe while a `created` checkout exists abandons the stale one.

Reconciliation: `php artisan membership:reconcile` (hourly) — abandons `created` past `checkout_expires_at`; re-fetches Razorpay subscriptions whose period ended beyond grace; expires admin grants past their end date. `--dry-run` supported.

---

## 6. API

| Method & path | Auth | Purpose |
|---|---|---|
| `GET /api/membership/plans` | optional | Catalogue: active plans, active prices, visible entitlements (display text + values), the caller's current plan code |
| `GET /api/membership` | jwt | Current plan, subscription state (status, renews/ends, cancel scheduled, pending change), every entitlement with limit + used |
| `POST /api/membership/subscribe` `{price_id}` | jwt, throttle:payments | Creates Razorpay subscription (new, upgrade, downgrade); returns `{subscription_id, key, amount_paise, plan, change_type, starts_at}` |
| `POST /api/membership/verify` `{razorpay_payment_id, razorpay_subscription_id, razorpay_signature}` | jwt, throttle:payments | HMAC(payment_id\|subscription_id) constant-time check, ownership check, then re-reads the subscription from Razorpay and syncs |
| `POST /api/membership/abandon` `{subscription_id}` | jwt | Checkout dismissed → cancel the `created` subscription |
| `POST /api/membership/cancel` `{at_period_end=true}` | jwt, throttle:payments | Cancels the live Razorpay subscription |
| `GET /api/membership/payments` | jwt | Billing history |
| `POST /api/webhooks/razorpay/members` | HMAC | Lifecycle events: `subscription.authenticated/activated/charged/pending/halted/cancelled/completed/expired/paused/resumed`, `payment.failed` (member notes only) |

---

## 7. Security

- Key secret and webhook secrets are server-only; the app receives the public key id only.
- Amount and plan are never taken from the client — only a `price_id`, resolved server-side to an active price with a Razorpay plan.
- `verify` rejects a subscription id that isn't the caller's (no IDOR), a bad signature (constant-time), and never trusts client status — Razorpay's own record is fetched.
- Webhook fails closed with no secret, verifies HMAC over the raw body, uses a secret distinct from the partner webhook.
- Row lock on the user during subscribe prevents double-checkout races; UNIQUE indexes make every grant idempotent.
- Admin actions (comp grant, revoke, cancel, override) are written to `member_subscription_events` with `actor_id`.

---

## 8. Android UX flow

```
Account ─► Membership card (plan · "Renews 12 Oct" / "Ends 12 Oct" / "Payment failed")
             │
             ▼
        Membership screen
          ├─ status banner (halted → "Your payment failed…", pending, cancel scheduled, change scheduled)
          ├─ Monthly | Yearly toggle (blue tint)
          ├─ Free / Pro / Hero cards — entitlement lines from the API, current plan marked
          ├─ select a card (blue border) → one green commit: "Get Hero · ₹249/month"
          │     └─ POST subscribe → Razorpay Checkout(subscription_id)
          │           ├─ success → POST verify → active ✓ (or "Confirming with your bank…" polling)
          │           ├─ dismissed → POST abandon
          │           └─ failed → inline error, retry
          ├─ Billing history
          └─ Cancel membership → confirm sheet with the real end date → POST cancel
```

Locked features surface the server's `upgrade_required` / `limit_reached` message; the profile's AI read shows a "Part of Pro" line with a route to Membership instead of silently disappearing.

---

## 9. /control management

- **Finance → Member plans** (super-admin): name/tagline/description/rank/active; prices (interval, amount, "Create Razorpay plan" action; amount locked once linked); entitlement grid — one row per feature with toggle or limit (blank = unlimited).
- **Finance → Member subscriptions** (finance + super-admin): filters by status/plan/provider; detail with timeline (events) and payments; actions — *Grant complimentary plan* (user, plan, until), *Extend*, *Revoke grant*, *Cancel at Razorpay* (now / cycle end), *Sync from Razorpay*.
- **Finance → Member entitlement overrides**: per-user feature comps with expiry and reason.
- **Finance → Member features**: display name/description/order/visibility of the catalogue.

---

## 10. Sport-specific advanced insights (added 2026-09-16)

| Key | Type | Free | Pro | Hero |
|---|---|---|---|---|
| `insights.advanced_sports` | limit (sports) | off (0) | 3 chosen sports | every sport (unlimited) |

- Sports: the eight scored sports (`Tournament::sportKeys()`): cricket, football, badminton, volleyball, basketball, kabaddi, tennis, table tennis.
- Choices live in `member_sport_selections` (unique user+sport; `created_at` = when chosen). A plan with every sport needs no rows. Choices persist through a lapse and apply again when the plan returns.
- `MemberEntitlements::assertChosen()` is the gate: unlimited → allow; 0 → `upgrade_required`; otherwise the sport must be among the member's first `limit` choices (oldest first) → else `selection_required`. Denials carry `sport`.
- `SportInsightsAccess` adds the selection rules: at most `limit`, known sports only, and a chosen sport is held `MEMBERSHIP_INSIGHT_SPORT_COOLDOWN_DAYS` (default 7) before it can be swapped out; empty slots fill any time; a member over a lowered limit may trim freely.
- Enforced before anything is built at: `GET /api/live-matches/{id}/insights` (cricket analysis + innings headlines + all sport builders), `GET /api/matches/{id}/iq` (Cricket IQ), and the web match page's Insights tab. Visibility is checked first, so hidden matches stay 404.
- Not gated (scorecard data, not insights): `/live-matches/{id}/player-stats`, `/matches/{id}/ground`.
- API: `GET /api/membership/insight-sports`, `PUT /api/membership/insight-sports {sports: []}`; `GET /api/membership` includes `insight_sports`.
