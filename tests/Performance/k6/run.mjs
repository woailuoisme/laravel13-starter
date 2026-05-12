import { existsSync } from 'node:fs';
import process from 'node:process';
import { spawnSync } from 'node:child_process';

const envFile = 'tests/Performance/k6/.env';
const profiles = {
  'mini-load': 'tests/Performance/k6/scenarios/mini-load.js',
  smoke: 'tests/Performance/k6/scenarios/smoke.js',
};

if (existsSync(envFile)) {
  process.loadEnvFile(envFile);
}

const profile = process.argv[2] || process.env.K6_PROFILE || process.env.K6_DEFAULT_PROFILE || 'smoke';
const scenario = profiles[profile];

if (!scenario) {
  console.error('Usage: node tests/Performance/k6/run.mjs [smoke|mini-load]');
  process.exit(64);
}

process.env.K6_PROFILE = profile;

const result = spawnSync('k6', ['run', scenario], {
  env: process.env,
  stdio: 'inherit',
});

if (result.error) {
  console.error('Install k6 v2.x on this computer before running performance tests.');
  process.exit(69);
}

process.exit(result.status ?? 1);
