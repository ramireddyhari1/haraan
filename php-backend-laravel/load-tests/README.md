# Load Tests

This folder contains basic k6 load tests for the Laravel API.

## What is covered

- Public browsing traffic against:
  - `GET /api/health`
  - `GET /api/events`
  - `GET /api/events/{id}`
  - `GET /api/events/categories`
- Authenticated traffic against:
  - `POST /api/auth/login`
  - `GET /api/auth/me`
  - `GET /api/bookings`
  - Optional `POST /api/bookings`

## High Load Ramp

Use [scripts/ramp-50-to-1000.js](scripts/ramp-50-to-1000.js) when you want a clearer performance curve from 50 to 1,000 concurrent users.

Stage plan:

| Stage | Concurrent users |
| --- | ---: |
| 1 | 50 |
| 2 | 100 |
| 3 | 150 |
| 4 | 200 |
| 5 | 250 |
| 6 | 300 |
| 7 | 350 |
| 8 | 400 |
| 9 | 450 |
| 10 | 500 |
| 11 | 550 |
| 12 | 600 |
| 13 | 650 |
| 14 | 700 |
| 15 | 750 |
| 16 | 800 |
| 17 | 850 |
| 18 | 900 |
| 19 | 950 |
| 20 | 1000 |

Recommended command:

```powershell
k6 run `
  -e BASE_URL=http://127.0.0.1:8000/api `
  -e AUTH_EMAIL=loadtest@example.com `
  -e AUTH_PASSWORD=password123 `
  -e START_USERS=50 `
  -e END_USERS=1000 `
  -e STEP_USERS=50 `
  -e STAGE_DURATION=60s `
  -e FINAL_STAGE_DURATION=60s `
  -e ITERATION_PAUSE_MS=20 `
  scripts/ramp-50-to-1000.js
```

## Results Template

Fill this in after the run to mark where performance starts to degrade:

| Stage | Users | Avg ms | p95 ms | p99 ms | req/s | Error rate | Notes |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Baseline | 50 |  |  |  |  |  |  |
| Stage 2 | 100 |  |  |  |  |  |  |
| Stage 3 | 150 |  |  |  |  |  |  |
| Stage 4 | 200 |  |  |  |  |  |  |
| Stage 5 | 250 |  |  |  |  |  |  |
| Stage 6 | 300 |  |  |  |  |  |  |
| Stage 7 | 350 |  |  |  |  |  |  |
| Stage 8 | 400 |  |  |  |  |  |  |
| Stage 9 | 450 |  |  |  |  |  |  |
| Stage 10 | 500 |  |  |  |  |  |  |
| Stage 11 | 550 |  |  |  |  |  |  |
| Stage 12 | 600 |  |  |  |  |  |  |
| Stage 13 | 650 |  |  |  |  |  |  |
| Stage 14 | 700 |  |  |  |  |  |  |
| Stage 15 | 750 |  |  |  |  |  |  |
| Stage 16 | 800 |  |  |  |  |  |  |
| Stage 17 | 850 |  |  |  |  |  |  |
| Stage 18 | 900 |  |  |  |  |  |  |
| Stage 19 | 950 |  |  |  |  |  |  |
| Stage 20 | 1000 |  |  |  |  |  |  |

Degradation threshold:

- Baseline p95 at 50 users: ____ ms
- First stage where p95 rises sharply: ____ users
- First stage where error rate exceeds 1%: ____ users
- First stage where throughput flattens while latency rises: ____ users
- Overall degradation point: ____ users

## Prerequisites

- Install [k6](https://grafana.com/docs/k6/latest/set-up/install-k6/)
- Start the Laravel API locally or point the tests at staging
- Have a valid test account for authenticated runs

## Quick start

Run these commands from the `php-backend-laravel/load-tests` directory.

Run the public browsing test:

```powershell
k6 run -e BASE_URL=http://127.0.0.1:8000/api scripts/basic-api-load.js
```

Run the authenticated flow:

```powershell
k6 run `
  -e BASE_URL=http://127.0.0.1:8000/api `
  -e AUTH_EMAIL=test@example.com `
  -e AUTH_PASSWORD=secret123 `
  scripts/auth-booking-load.js
```

Enable booking creation only when you are testing a safe environment:

```powershell
k6 run `
  -e BASE_URL=http://127.0.0.1:8000/api `
  -e AUTH_EMAIL=test@example.com `
  -e AUTH_PASSWORD=secret123 `
  -e CREATE_BOOKINGS=true `
  -e EVENT_ID=1 `
  scripts/auth-booking-load.js
```

## Useful environment variables

- `BASE_URL`: API base URL. Default: `http://127.0.0.1:8000/api`
- `AUTH_EMAIL`: test user email for the authenticated script
- `AUTH_PASSWORD`: test user password for the authenticated script
- `EVENT_ID`: target event for booking creation. If omitted, the script uses the first event returned by `/api/events?limit=1`
- `CREATE_BOOKINGS`: set to `true` to create bookings during the test
- `QUANTITY`: booking quantity when `CREATE_BOOKINGS=true`
- `VUS_WARMUP`: warm-up virtual users for the ramping stage
- `VUS_PEAK`: peak virtual users for the ramping stage
- `ITERATION_PAUSE_MS`: pause between iterations in milliseconds. Default: `20`
- `SEARCH_TERM`: search term used by the public browsing test

## Recommended approach

1. Run `basic-api-load.js` first to establish baseline latency and error rate.
2. Run `auth-booking-load.js` next to test authenticated traffic.
3. Only enable `CREATE_BOOKINGS=true` in a staging or dedicated test environment.
4. Watch p95 latency, failure rate, and backend DB CPU during the run.
