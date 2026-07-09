# Haraan — AWS → DigitalOcean Migration Plan (sized for ~10k users)

**Date:** 2026-07-08
**Goal:** Move off AWS EC2 onto **one** DigitalOcean account, on an architecture that comfortably
serves ~10k registered users (~1k–2k concurrent at peak) and scales to 100k by *adding* servers —
no rewrite needed later.
**Region:** **BLR1 (Bangalore)** — lowest latency for your India/district users (AWS was Mumbai).

> Uses ONE account's $200 credit. The credit lasts ~2–3 months at this size; cost grows only as
> users do.

---

## Target architecture (~10k users)

```
                  Users (Android app / web)
                          │  HTTPS
                          ▼
                 ┌──────────────────┐
                 │   1 Droplet      │  Ubuntu 24.04, 4 vCPU / 8 GB, BLR1
                 │  nginx + php-fpm │
                 │  Reverb (WS)     │
                 │  queue worker    │
                 │  redis (local)   │
                 └───────┬──────────┘
              ┌──────────┴───────────┐
              ▼                      ▼
   ┌────────────────────┐   ┌────────────────────┐
   │ Managed Postgres   │   │ Spaces + CDN        │
   │ (backups + PITR)   │   │ (venue/team images) │
   └────────────────────┘   └────────────────────┘
```

### Components & cost

| Component | Spec | ~$/mo | Notes |
|-----------|------|-------|-------|
| Droplet | 4 vCPU / 8 GB, BLR1 | ~$48 | nginx + php-fpm + Reverb + queue worker + local Redis |
| **Managed Postgres** | 1 vCPU / 1–2 GB | ~$15 | The upgrade that removes SQLite's single-writer ceiling. Managed backups + point-in-time restore. |
| Spaces + CDN | 250 GB + 1 TB transfer | ~$5 | Images off the server, served fast |
| Domain | Namecheap (free in Student Pack) | $0 | Needed for HTTPS |
| HTTPS | Let's Encrypt | $0 | |
| **Total** | | **~$68/mo** | $200 credit ≈ **~3 months** |

> **Lean vs robust:** at 10k users, running **Redis on the droplet** (not a separate Managed Redis)
> is fine and saves ~$15/mo. Add Managed Redis only when you add a *second* app droplet.

---

## App changes required (the real work — I do these in code)

These are the single-box shortcuts from your capacity report; the migration fixes them:

1. **SQLite → Postgres** — `config/database.php` already prepped; port schema + data. *(the ceiling)*
2. **`sync` queue → Redis queue + worker** — OTP emails / broadcasts run async, off the web request.
3. **File cache + sessions → Redis** — required before you can ever add a 2nd app server.
4. **Local uploads → Spaces (S3 driver)** — images on object storage + CDN, not the droplet disk.
5. **HTTPS + domain** — mandatory (also unlocks in-app QR camera).
6. **Backups + monitoring** — Managed Postgres auto-backups + DO monitoring/uptime alerts.

---

## Phased execution

### Phase 0 — Code prep (no DO access needed — I can start now)
- [ ] **Postgres-readiness audit:** scan migrations + raw queries for SQLite-isms
      (booleans, JSON columns, `lower(status)` casing spots, `INTEGER PRIMARY KEY`, date funcs).
- [ ] Add `pgsql` connection wiring + `.env.example` keys.
- [ ] Switch `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` → `redis`.
- [ ] Add Spaces (S3) filesystem disk; point uploads at it.
- [ ] Write systemd unit templates: `haraan-reverb`, `haraan-queue`, scheduler cron.

### Phase 1 — Provision on DigitalOcean (needs your account)
- [ ] Droplet: Ubuntu 24.04, 4 vCPU / 8 GB, **BLR1**.
- [ ] Managed Postgres cluster, **BLR1**, same VPC as droplet.
- [ ] Spaces bucket + CDN endpoint, generate access keys.
- [ ] Point domain's A record → droplet IP.

### Phase 2 — Server setup
- [ ] `nginx`, `php8.5-fpm` + extensions (incl. `pgsql`, `redis`), `composer`, `redis-server`.
- [ ] Deploy app; `.env` for Postgres, Redis, Spaces, Reverb, mail, `APP_URL=https://...`.
- [ ] **Let's Encrypt HTTPS** via certbot.
- [ ] Tuned php-fpm pool (max_children ~30, as we already did on AWS).

### Phase 3 — Data migration (SQLite → Postgres)
- [ ] `php artisan migrate` fresh on Postgres.
- [ ] Port data (script: read SQLite rows → insert into PG; handle bool/JSON/timestamps).
- [ ] Verify row counts per table + spot-check venues/users/matches.

### Phase 4 — Realtime + jobs
- [ ] `haraan-reverb` (WebSockets), `haraan-queue` (worker), scheduler cron — all as systemd.
- [ ] Verify live scores push + OTP email async.

### Phase 5 — App + cutover
- [ ] Rebuild Android APK with `API_BASE_URL=https://<domain>`; host new `haraan.apk`.
- [ ] End-to-end verify: login (OTP), home layout, venues, live scoring, images.
- [ ] Flip DNS / announce; monitor 24–48h.
- [ ] **Decommission AWS** once stable.

---

## Capacity this buys you
- **~10k registered / ~1–2k concurrent:** comfortable on the single droplet above.
- **Path to 100k (no rewrite):** add a Load Balancer + 1–2 more app droplets + a Managed Redis +
  a Postgres read replica. Because sessions/cache/queue already live in Redis and data in Postgres,
  the app droplets are stateless — you just add more of them.

---

## What I can start immediately
Phase 0 needs **no DigitalOcean access** — it's all code. I can begin the **Postgres-readiness
audit** now and wire up the Redis/Spaces config, so the moment you provision the droplet, deploy is
fast.

**To provision (Phase 1), I'll need from you:**
1. A domain name (grab the free Namecheap one from the Student Pack if you don't have one).
2. Which single DO account we're using.

*Generated as the migration blueprint for the ~10k-user tier.*
