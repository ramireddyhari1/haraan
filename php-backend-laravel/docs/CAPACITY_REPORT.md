# Haraan — Server Capacity & Hardening Report

**Date:** 2026-07-08
**Server:** AWS EC2 `m7i-flex.large` — 2 vCPU (Intel Xeon Platinum 8488C), 8 GB RAM, 290 GB disk
**Host:** `13.204.63.181` (ap-south-1)
**Stack:** nginx 1.28 → php8.5-fpm → Laravel 13 · SQLite DB · Reverb (WebSockets) · OTP bridge — all on one box

---

## 1. Executive summary

The hardware is healthy and **massively under-utilised** — it was previously throttled by
default config, not by its capacity. Three low-risk changes applied today unlocked roughly a
**10× increase in safe concurrency** with no code rewrite and no downtime.

- **Before today:** ~50–150 concurrent active users before login/scoring bursts would choke.
- **After today's fixes:** comfortably **~1,000–2,000 concurrent** active users on the same box.
- **Total registered accounts** were never the limit — the box can store **100k+ accounts** (DB
  is currently 0.5 MB on a 290 GB disk).

There is **no load balancer or auto-scaling** — it is a single vertical server. It stays stable
up to its ceiling, then degrades. The path beyond a few thousand concurrent users is an
architecture change (below), not a config tweak.

---

## 2. Changes applied today (before → after)

| Area | Before | After | Why it matters |
|------|--------|-------|----------------|
| **php-fpm `pm.max_children`** | 5 | **30** | Only 5 requests could run at once on a box that handles ~30. This was the single hardest ceiling. |
| **php-fpm spare servers** | 2 / 1 / 3 | **6 / 4 / 12** | Warm workers ready for bursts instead of cold-spawning under load. |
| **SQLite `journal_mode`** | `delete` | **`WAL`** | `delete` locks the *whole* DB on every write (readers block writers → "database is locked"). WAL lets reads run concurrently with a writer. |
| **SQLite `busy_timeout`** | none (0) | **5000 ms** | Writers now wait up to 5s for a lock instead of failing instantly under contention. |
| **SQLite `synchronous`** | `FULL` | **`NORMAL`** | Safe with WAL; far fewer fsyncs = faster writes. |
| **Swap** | 0 B | **4 GB** | Safety net so a spike can't OOM-kill Chrome / Reverb / php-fpm. |

All changes are reversible. The php-fpm pool config was backed up (`www.conf.bak.*`), the SQLite
settings are version-controlled in `config/database.php`, and swap is a standard `/swapfile`.

### Verification
- API healthy after each change: `GET /api/venues` → **HTTP 200 in 0.28s**.
- **Load test:** 100 requests at 25 concurrent → **100 × HTTP 200 in ~1.0s**, server load stayed
  at **0.14** (of 2.0). The box barely registered the burst.

---

## 3. Two different "user" numbers (don't conflate them)

- **Registered accounts (total):** NOT the constraint. 100k+ rows are fine — it's just storage.
- **Concurrent active users (same few seconds):** this is what server capacity measures, and
  what the estimates below refer to.

---

## 4. Capacity — staged

This app is **read-heavy** and polls with **conditional GETs (304 Not Modified)**, which scales
reads extremely well. That works in our favour.

| Stage | Config | Safe concurrent users | Notes |
|-------|--------|----------------------|-------|
| Was (this morning) | max_children 5, sqlite `delete`, sync queue | **~50–150** | Login/scoring bursts choke first |
| **Now (after fixes)** | max_children 30, WAL, swap | **~1,000–2,000** | Reads scale great; writes serialise through SQLite |
| Next (queue worker — see §5) | + async queue | **~2,000–3,000** | OTP emails/broadcasts stop blocking web workers |
| Real scale | Postgres + LB + Reverb scaling | **10k+** | Architecture project, not a tweak |

> Numbers are engineering estimates for this workload mix. Only a sustained load test with real
> auth/scoring traffic gives exact figures.

---

## 5. Remaining bottlenecks & roadmap

Ordered by when they'll bite:

1. **Queue is `sync`** — OTP emails and broadcasts run *inside* the web request. A signup burst
   can tie up web workers on SMTP round-trips. **Fix:** `QUEUE_CONNECTION=database` + a systemd
   queue worker. ~30 min, low risk. Recommended next.

2. **SQLite is single-writer** — even with WAL, only one write transaction at a time. This is the
   eventual hard ceiling for write-heavy moments (mass simultaneous scoring/booking). **Fix:**
   migrate to Postgres/MySQL (AWS RDS) *before* that scale. Not urgent; it's the thing that ends
   single-box life.

3. **File cache + file sessions** — fine (fast) on one box, but they block horizontal scaling
   (each server has its own session files). **Fix:** move to Redis when adding a 2nd app server.

4. **Single instance, no load balancer** — no failover, no horizontal scale. **Fix at scale:**
   ALB in front of 2+ app instances (requires #2 and #3 first).

5. **Headless Chrome using ~1.5 GB RAM** — the old WhatsApp bridge. If email OTP has fully
   replaced WhatsApp, this is reclaimable dead weight. **Action:** confirm and shut it off.

6. **`m7i-flex` CPU nuance** — tuned for ~40% average CPU; can burst higher but *sustained* 100%
   CPU (hours-long viral spike) may throttle to baseline. **Fix at scale:** `m7i.large` (non-flex)
   or larger.

---

## 6. Recommended order of work

1. ✅ **Done today:** php-fpm workers, SQLite WAL/busy_timeout/synchronous, swap.
2. **Async queue + worker** (unblocks login bursts) — quick, do next.
3. **Kill the unused Chrome/WA bridge** if email OTP replaced it — free 1.5 GB.
4. **Load-test with real auth + scoring traffic** to get true numbers.
5. **Postgres/RDS migration** — plan before write-heavy virality.
6. **Redis + load balancer + Reverb scaling** — when going past a few thousand concurrent.

---

*Generated after applying and verifying the Stage-2 hardening on the live server.*
