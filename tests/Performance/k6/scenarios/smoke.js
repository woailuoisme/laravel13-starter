import { smokeProfile } from '../config/profiles.js';
import { getJson } from '../lib/http.js';

export const options = smokeProfile;

export default function () {
  getJson('/api', { flow: 'public-api-smoke' });
}
