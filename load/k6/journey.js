// k6 run -e BASE=https://staging.baree.example -e PASSWORD=LoadTest-2026 load/k6/journey.js
// Realistic teacher journey. Run against STAGING with AI providers on the fake driver.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const users = new SharedArray('users', () => JSON.parse(open('./users.json')));
const BASE = __ENV.BASE;

export const options = {
  scenarios: {
    school_morning: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '2m', target: 200 }, { duration: '10m', target: 500 }, { duration: '2m', target: 0 }] },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{page:feed}': ['p(95)<600'],
    'http_req_duration{page:studio}': ['p(95)<600'],
    'http_req_duration{page:hub}': ['p(95)<1500'],
  },
};

function xsrf(jar) {
  const cookie = jar.cookiesForURL(BASE)['XSRF-TOKEN'];
  return cookie ? decodeURIComponent(cookie[0]) : '';
}

export default function () {
  const jar = http.cookieJar();
  const email = users[(__VU - 1) % users.length];
  http.get(`${BASE}/login`);
  const login = http.post(`${BASE}/login`, { email, password: __ENV.PASSWORD }, { headers: { 'X-XSRF-TOKEN': xsrf(jar) }, redirects: 0 });
  check(login, { 'logged in': (r) => r.status === 302 || r.status === 303 });

  const inertia = { 'X-Inertia': 'true', 'X-Requested-With': 'XMLHttpRequest' };
  check(http.get(`${BASE}/home`, { headers: inertia, tags: { page: 'feed' } }), { feed: (r) => r.status === 200 || r.status === 409 });
  sleep(2);
  check(http.get(`${BASE}/studio`, { headers: inertia, tags: { page: 'studio' } }), { studio: (r) => r.status === 200 || r.status === 409 });
  sleep(3);
  check(http.get(`${BASE}/studio/tools/worksheet`, { headers: inertia, tags: { page: 'studio' } }), { tool: (r) => r.status === 200 || r.status === 409 });
  const run = http.post(`${BASE}/studio/tools/worksheet/runs`, JSON.stringify({ inputs: { grade: '8', subject: 'رياضيات', topic: 'المعادلات', items: 5 } }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(jar) }, tags: { page: 'studio' } });
  check(run, { 'run queued': (r) => r.status === 202 });
  sleep(5);
  const ask = http.post(`${BASE}/hub/ask`, JSON.stringify({ question: 'هل بقدر أستخدم أدوات AI مع بيانات الطلاب؟' }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(jar) }, tags: { page: 'hub' } });
  check(ask, { 'hub answered': (r) => r.status === 200 });
  sleep(5);
}
