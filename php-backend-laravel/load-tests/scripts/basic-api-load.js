import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const SEARCH_TERM = __ENV.SEARCH_TERM || 'Live';
const WARMUP_DURATION = __ENV.WARMUP_DURATION || '30s';
const PEAK_DURATION = __ENV.PEAK_DURATION || '2m';
const COOLDOWN_DURATION = __ENV.COOLDOWN_DURATION || '30s';
const ITERATION_PAUSE_MS = Number(__ENV.ITERATION_PAUSE_MS || 20);

export const options = {
  scenarios: {
    public_browse: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: WARMUP_DURATION, target: Number(__ENV.VUS_WARMUP || 10) },
        { duration: PEAK_DURATION, target: Number(__ENV.VUS_PEAK || 50) },
        { duration: PEAK_DURATION, target: Number(__ENV.VUS_PEAK || 50) },
        { duration: COOLDOWN_DURATION, target: 0 },
      ],
      gracefulRampDown: '30s',
      exec: 'publicBrowse',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800'],
  },
};

export function publicBrowse() {
  const health = http.get(`${BASE_URL}/health`);
  check(health, {
    'health returns 200': (res) => res.status === 200,
  });

  const categories = http.get(`${BASE_URL}/events/categories`);
  check(categories, {
    'categories returns 200': (res) => res.status === 200,
  });

  const browse = http.get(`${BASE_URL}/events?limit=12&search=${encodeURIComponent(SEARCH_TERM)}`);
  check(browse, {
    'events list returns 200': (res) => res.status === 200,
    'events list has data': (res) => Array.isArray(res.json('data')),
  });

  const items = browse.json('data') || [];
  if (items.length > 0) {
    const selected = items[Math.floor(Math.random() * items.length)];
    const detail = http.get(`${BASE_URL}/events/${selected.id}`);
    check(detail, {
      'event detail returns 200': (res) => res.status === 200,
    });
  }

  sleep(ITERATION_PAUSE_MS / 1000);
}
