const { defineConfig } = require('cypress');

const { VueLoaderPlugin } = require('vue-loader');

module.exports = defineConfig({
  fixturesFolder: 'tests/cypress/fixtures',
  screenshotsFolder: 'tests/cypress/screenshots',
  videosFolder: 'tests/cypress/videos',
  downloadsFolder: 'tests/cypress/downloads',
  trashAssetsBeforeRuns: true,
  pageLoadTimeout: 300000,
  e2e: {
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
    baseUrl: 'http://localhost:8080',
    specPattern: 'tests/cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/cypress/support/e2e.js',
    experimentalStudio: true,
  },
  component: {
    specPattern: 'tests/cypress/component/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/cypress/support/component.js',
    indexHtmlFile: 'tests/cypress/support/component-index.html',
    devServer: {
      framework: 'vue',
      bundler: 'webpack',
      webpackConfig: {
        mode: 'development',
        module: {
          rules: [
            {
              test: /\.vue$/,
              loader: 'vue-loader',
            },
            // this will apply to both plain `.js` files
            // AND `<script>` blocks in `.vue` files
            {
              test: /\.js$/,
              loader: 'babel-loader',
            },
            // this will apply to both plain `.css` files
            // AND `<style>` blocks in `.vue` files
            {
              test: /\.css$/,
              use: [
                'vue-style-loader',
                'css-loader',
              ],
            },
          ],
        },
        plugins: [
          new VueLoaderPlugin(),
        ],
      },
    },
  },
});
