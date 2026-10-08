import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // Vitest only excludes node_modules and .git by default, so scope test
    // discovery explicitly to keep it out of vendor/ and the Cypress suites.
    include: ['tests/Spec/**/*.spec.js'],
  },
});
