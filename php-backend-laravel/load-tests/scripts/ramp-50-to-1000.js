import http from 'k6/http';
import exec from 'k6/execution';
import { check, fail, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const AUTH_EMAIL = __ENV.AUTH_EMAIL || '';
const AUTH_PASSWORD = __ENV.AUTH_PASSWORD || '';
const CREATE_BOOKINGS = (__ENV.CREATE_BOOKINGS || 'true').toLowerCase() === 'true';
const WRITE_EVERY_N_ITERATIONS = Number(__ENV.WRITE_EVERY_N_ITERATIONS || 25);
const ITERATION_PAUSE_MS = Number(__ENV.ITERATION_PAUSE_MS || 20);
const START_USERS = Number(__ENV.START_USERS || 50);
const END_USERS = Number(__ENV.END_USERS || 1000);
const STEP_USERS = Number(__ENV.STEP_USERS || 50);
const STAGE_DURATION = __ENV.STAGE_DURATION || '60s';
const FINAL_STAGE_DURATION = __ENV.FINAL_STAGE_DURATION || STAGE_DURATION;
const P95_THRESHOLD_MS = Number(__ENV.P95_THRESHOLD_MS || 2000);

function buildStages() {
  const stages = [];

  for (let users = START_USERS; users < END_USERS; users += STEP_USERS) {
    stages.push({ duration: STAGE_DURATION, target: users });
  }

  stages.push({ duration: FINAL_STAGE_DURATION, target: END_USERS });
  return stages;
}

export const options = {
  scenarios: {
    ramp_50_to_1000: {
      executor: 'ramping-vus',
      startVUs: START_USERS,
      stages: buildStages(),
      gracefulRampDown: '30s',
      exec: 'rampThroughApi',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: [`p(95)<${P95_THRESHOLD_MS}`],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
};

function login() {
  if (!AUTH_EMAIL || !AUTH_PASSWORD) {
    fail('Set AUTH_EMAIL and AUTH_PASSWORD before running ramp-50-to-1000.js');
  }

  const response = http.post(
    `${BASE_URL}/auth/login`,
    JSON.stringify({ email: AUTH_EMAIL, password: AUTH_PASSWORD }),
    { headers: { 'Content-Type': 'application/json' } },
  );

  check(response, {
    'login returns 200': (res) => res.status === 200,
    'login includes token': (res) => Boolean(res.json('token')),
  }) || fail(`Login failed with status ${response.status}`);

  return response.json('token');
}

export function setup() {
  const token = login();

  const eventsResponse = http.get(`${BASE_URL}/events?limit=1`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  check(eventsResponse, {
    'event lookup returns 200': (res) => res.status === 200,
  }) || fail(`Event lookup failed with status ${eventsResponse.status}`);

  const firstEvent = (eventsResponse.json('data') || [])[0];
  if (!firstEvent || !firstEvent.id) {
    fail('No events returned by /events?limit=1. Seed at least one event before running the ramp test.');
  }

  return {
    token,
    eventId: Number(firstEvent.id),
  };
}

export function rampThroughApi(data) {
  const headers = {
    Authorization: `Bearer ${data.token}`,
    'Content-Type': 'application/json',
  };

  const health = http.get(`${BASE_URL}/health`);
  check(health, {
    'health returns 200': (res) => res.status === 200,
  });

  const categories = http.get(`${BASE_URL}/events/categories`);
  check(categories, {
    'categories returns 200': (res) => res.status === 200,
  });

  const browse = http.get(`${BASE_URL}/events?limit=12&search=${encodeURIComponent(__ENV.SEARCH_TERM || 'Live')}`);
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

  const meResponse = http.get(`${BASE_URL}/auth/me`, { headers });
  check(meResponse, {
    'me returns 200': (res) => res.status === 200,
  });

  const bookingsResponse = http.get(`${BASE_URL}/bookings?limit=10`, { headers });
  check(bookingsResponse, {
    'bookings list returns 200': (res) => res.status === 200,
  });

  if (CREATE_BOOKINGS && exec.vu.iterationInScenario % WRITE_EVERY_N_ITERATIONS === 0) {
    const bookingResponse = http.post(
      `${BASE_URL}/bookings`,
      JSON.stringify({
        eventId: data.eventId,
        quantity: 1,
        seatNumbers: [],
      }),
      { headers },
    );

    check(bookingResponse, {
      'booking create returns 201 or 422': (res) => res.status === 201 || res.status === 422,
    });
  }

  sleep(ITERATION_PAUSE_MS / 1000);
}