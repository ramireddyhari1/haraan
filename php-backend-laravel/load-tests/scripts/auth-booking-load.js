import http from 'k6/http';
import { check, fail, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const AUTH_EMAIL = __ENV.AUTH_EMAIL || '';
const AUTH_PASSWORD = __ENV.AUTH_PASSWORD || '';
const PROVIDED_EVENT_ID = __ENV.EVENT_ID ? Number(__ENV.EVENT_ID) : null;
const CREATE_BOOKINGS = (__ENV.CREATE_BOOKINGS || 'false').toLowerCase() === 'true';
const QUANTITY = Number(__ENV.QUANTITY || 1);
const WARMUP_DURATION = __ENV.WARMUP_DURATION || '30s';
const PEAK_DURATION = __ENV.PEAK_DURATION || '2m';
const COOLDOWN_DURATION = __ENV.COOLDOWN_DURATION || '30s';
const ITERATION_PAUSE_MS = Number(__ENV.ITERATION_PAUSE_MS || 20);

export const options = {
  scenarios: {
    authenticated_flow: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: WARMUP_DURATION, target: Number(__ENV.VUS_WARMUP || 5) },
        { duration: PEAK_DURATION, target: Number(__ENV.VUS_PEAK || 25) },
        { duration: PEAK_DURATION, target: Number(__ENV.VUS_PEAK || 25) },
        { duration: COOLDOWN_DURATION, target: 0 },
      ],
      gracefulRampDown: '30s',
      exec: 'authenticatedFlow',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
  },
};

export function setup() {
  if (!AUTH_EMAIL || !AUTH_PASSWORD) {
    fail('Set AUTH_EMAIL and AUTH_PASSWORD before running auth-booking-load.js');
  }

  const loginResponse = http.post(
    `${BASE_URL}/auth/login`,
    JSON.stringify({ email: AUTH_EMAIL, password: AUTH_PASSWORD }),
    { headers: { 'Content-Type': 'application/json' } },
  );

  check(loginResponse, {
    'login returns 200': (res) => res.status === 200,
    'login includes token': (res) => Boolean(res.json('token')),
  }) || fail(`Login failed with status ${loginResponse.status}`);

  let eventId = PROVIDED_EVENT_ID;
  if (!eventId) {
    const eventsResponse = http.get(`${BASE_URL}/events?limit=1`);
    check(eventsResponse, {
      'events lookup returns 200': (res) => res.status === 200,
    }) || fail(`Event lookup failed with status ${eventsResponse.status}`);

    const firstEvent = (eventsResponse.json('data') || [])[0];
    if (!firstEvent || !firstEvent.id) {
      fail('No events returned by /events?limit=1. Set EVENT_ID manually.');
    }

    eventId = Number(firstEvent.id);
  }

  return {
    token: loginResponse.json('token'),
    eventId,
  };
}

export function authenticatedFlow(data) {
  const headers = {
    Authorization: `Bearer ${data.token}`,
    'Content-Type': 'application/json',
  };

  const meResponse = http.get(`${BASE_URL}/auth/me`, { headers });
  check(meResponse, {
    'me returns 200': (res) => res.status === 200,
  });

  const bookingsResponse = http.get(`${BASE_URL}/bookings?limit=10`, { headers });
  check(bookingsResponse, {
    'bookings list returns 200': (res) => res.status === 200,
  });

  if (CREATE_BOOKINGS) {
    const bookingResponse = http.post(
      `${BASE_URL}/bookings`,
      JSON.stringify({
        eventId: data.eventId,
        quantity: QUANTITY,
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
