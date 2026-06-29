const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

const port = process.env.PHPUPLOADER_E2E_PORT || '8765';
const baseURL = `http://127.0.0.1:${port}`;
const testConfigPath = path.join('.test-runtime', 'config', 'config.php');
const webServerCommand = process.env.PHPUPLOADER_E2E_USE_DOCKER === '1'
  ? [
      'docker compose --profile tools run --rm',
      `-p ${port}:${port}`,
      `-e PHPUPLOADER_CONFIG_PATH=${testConfigPath}`,
      'php-cli',
      `php -S 0.0.0.0:${port} -t .`
    ].join(' ')
  : `php -S 127.0.0.1:${port} -t .`;

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: {
    timeout: 7_500
  },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI
    ? [['github'], ['list'], ['html', { open: 'never' }]]
    : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure'
  },
  webServer: {
    command: webServerCommand,
    url: `${baseURL}/index.php`,
    timeout: 120_000,
    reuseExistingServer: !process.env.CI,
    env: {
      PHPUPLOADER_CONFIG_PATH: testConfigPath
    }
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome']
      }
    }
  ]
});
