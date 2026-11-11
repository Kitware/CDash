const { defineConfig } = require('cypress');

module.exports = defineConfig({
  fixturesFolder: 'tests/cypress/fixtures',
  screenshotsFolder: 'tests/cypress/screenshots',
  videosFolder: 'tests/cypress/videos',
  downloadsFolder: 'tests/cypress/downloads',
  trashAssetsBeforeRuns: true,
  pageLoadTimeout: 300000,
  defaultCommandTimeout: 30000,
  e2e: {
    setupNodeEvents(on) {
      on('after:screenshot', (details) => {
        console.log(`\n<CTestMeasurementFile name="TestImage" type="image/png">${details.path}</CTestMeasurementFile>\n`);
      });
    },
    baseUrl: 'http://localhost:8080',
    specPattern: 'tests/cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/cypress/support/e2e.js',
    experimentalStudio: true,
  },
});
