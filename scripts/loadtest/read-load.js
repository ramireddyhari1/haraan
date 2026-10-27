// Haraan read-path load test (k6). Simulates app users sitting on the polled screens:
// GameHub (venues + live matches every ~20s), Events (every ~30s) and the public web pages.
//
//   k6 run scripts/loadtest/read-load.js                              # local, http://127.0.0.1:8000
//   k6 run -e BASE_URL=https://staging.example -e PEAK=200 scripts/loadtest/read-load.js
//
// PEAK = concurrent virtual users at the top of the ramp. Each VU behaves like one phone,
// so PEAK=200 ≈ 200 people with the app open at the same moment.
// Do NOT point this at production without planning a quiet window first.
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const PEAK = parseInt(__ENV.PEAK || '50', 10);

export const options = {
  scenarios: {
    app_users: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '1m', target: Math.ceil(PEAK / 2) },
        { duration: '2m', target: PEAK },
        { duration: '2m', target: PEAK },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],          // <1% errors (429s from the rate limiter count)
    http_req_duration: ['p(95)<800'],        // 95% of requests under 800 ms
    'http_req_duration{kind:api}': ['p(95)<500'],
  },
};

const headers = { 'X-App-Version': '99.0.0', Accept: 'application/json' };

export default function () {
  // One "poll tick" of the GameHub + Events tabs.
  const api = http.batch([
    ['GET', `${BASE}/api/venues`, null, { headers, tags: { kind: 'api', name: 'venues' } }],
    ['GET', `${BASE}/api/live-matches`, null, { headers, tags: { kind: 'api', name: 'live-matches' } }],
    ['GET', `${BASE}/api/events`, null, { headers, tags: { kind: 'api', name: 'events' } }],
    ['GET', `${BASE}/api/config`, null, { headers, tags: { kind: 'api', name: 'config' } }],
  ]);
  api.forEach((r) => check(r, { 'api 200': (res) => res.status === 200 }));

  // One in five ticks a web visitor loads a public page.
  if (Math.random() < 0.2) {
    const page = ['/', '/gamehub', '/gamehub/actionboard', '/events'][Math.floor(Math.random() * 4)];
    const r = http.get(`${BASE}${page}`, { tags: { kind: 'web', name: page } });
    check(r, { 'web 200': (res) => res.status === 200 });
  }

  // Real phones poll every ~20s; compressed to 5–10s so a short test still applies pressure.
  sleep(5 + Math.random() * 5);
}
