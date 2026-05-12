export const performanceConfig = {
  baseUrl: __ENV.K6_BASE_URL || 'http://localhost:8000',
};

export const miniLoadConfig = {
  targetUrl: __ENV.K6_MINI_LOAD_URL || 'http://0.0.0.0:8001/api',
};

export const smokeProfile = {
  vus: 1,
  duration: '15s',
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: true }],
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
  },
};

export const miniLoadProfile = {
  vus: 100,
  duration: '1m',
  thresholds: {
    checks: ['rate>0.99'],
    http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: true }],
    http_req_duration: ['p(95)<750', 'p(99)<1500'],
  },
};
