import { miniLoadConfig, miniLoadProfile } from '../config/profiles.js';
import { getJsonUrl } from '../lib/http.js';

export const options = miniLoadProfile;

export default function () {
  getJsonUrl(miniLoadConfig.targetUrl, {
    endpoint: '/api',
    flow: 'root-api-mini-load',
  });
}
