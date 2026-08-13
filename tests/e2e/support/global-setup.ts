import { execFileSync } from 'node:child_process';
import { laravelE2EEnv } from '../../../playwright.config';

const destructiveDbPattern = /(test|testing|e2e)/i;

function runArtisan(args: string[]) {
  execFileSync('php', ['artisan', ...args], {
    env: laravelE2EEnv,
    stdio: 'inherit',
  });
}

async function globalSetup() {
  if (process.env.E2E_SKIP_DB_SETUP === '1') {
    return;
  }

  const databaseName = laravelE2EEnv.DB_DATABASE ?? '';
  const allowDestructive = process.env.E2E_ALLOW_DESTRUCTIVE_DB === '1';

  if (!destructiveDbPattern.test(databaseName) && !allowDestructive) {
    throw new Error(
      [
        `Refusing to run migrate:fresh against DB_DATABASE="${databaseName}".`,
        'Use a database name containing test/testing/e2e, or set E2E_ALLOW_DESTRUCTIVE_DB=1 deliberately.',
      ].join(' '),
    );
  }

  runArtisan(['view:clear']);
  runArtisan(['config:clear']);
  runArtisan(['migrate:fresh', '--seed', '--force']);
  runArtisan(['db:seed', '--class=E2ECourseOfferingSeeder', '--force']);
  runArtisan(['db:seed', '--class=WorkloadPagePreviewSeeder', '--force']);
}

export default globalSetup;
