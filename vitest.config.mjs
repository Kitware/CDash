import { resolve } from 'node:path';
import vue from '@vitejs/plugin-vue';
import { webdriverio } from '@vitest/browser-webdriverio';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    reporters: [
      'default',
      {
        // Attach failure screenshots to the test results CTest submits to CDash.
        onTestCaseArtifactRecord(testCase, artifact) {
          if (artifact.type !== 'internal:failureScreenshot') {
            return;
          }
          for (const attachment of artifact.attachments) {
            console.log(`\n<CTestMeasurementFile name="TestImage" type="image/png">${resolve(attachment.path)}</CTestMeasurementFile>\n`);
          }
        },
      },
    ],
    projects: [
      {
        test: {
          name: 'unit',
          // Vitest only excludes node_modules and .git by default, so scope test
          // discovery explicitly to keep it out of vendor/ and the Cypress suites.
          include: ['tests/Spec/**/*.spec.js'],
        },
      },
      {
        plugins: [vue()],
        // Vite only scans <script> blocks for dependencies, so it misses the vue
        // imports in compiled templates and serves a second copy of vue next to
        // the one bundled with @vue/test-utils.
        optimizeDeps: {
          include: ['vue'],
        },
        // The browser runs in the selenium container, so it loads the tests from
        // this container by its docker compose service name.
        server: {
          allowedHosts: ['website'],
        },
        test: {
          name: 'component',
          include: ['tests/Component/**/*.spec.js'],
          api: {
            host: 'website',
            // Only reachable from the docker compose network.
            allowWrite: true,
            allowExec: true,
          },
          browser: {
            enabled: true,
            headless: true,
            provider: webdriverio({
              hostname: 'selenium',
              port: 4444,
              path: '/wd/hub',
            }),
            instances: [{ browser: 'chrome' }],
            // Recording a trace serializes the locator of every expect.element(),
            // which the webdriverio provider can only do for elements that exist,
            // so .not.toBeInTheDocument() would always fail.
            traceView: 'off',
            locators: {
              testIdAttribute: 'data-test',
            },
          },
        },
      },
    ],
  },
});
