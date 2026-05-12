import { check } from 'k6';
import http from 'k6/http';

import { performanceConfig } from '../config/profiles.js';

export const baseUrl = performanceConfig.baseUrl.replace(/\/+$/, '');

export const jsonHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

export function url(path) {
  return `${baseUrl}/${path.replace(/^\/+/, '')}`;
}

export function getJson(path, tags = {}) {
  return getJsonUrl(url(path), {
    endpoint: path,
    ...tags,
  });
}

export function getJsonUrl(requestUrl, tags = {}) {
  const response = http.get(requestUrl, {
    headers: jsonHeaders,
    tags,
  });

  check(response, {
    'status is 2xx': (res) => res.status >= 200 && res.status < 300,
    'content type is json': (res) => String(res.headers['Content-Type'] || '').includes('application/json'),
  });

  return response;
}
