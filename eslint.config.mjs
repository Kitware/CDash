import js from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import pluginCypress from 'eslint-plugin-cypress';
import pluginJest from 'eslint-plugin-jest';
import { defineConfig, globalIgnores } from 'eslint/config';

export default defineConfig([
  js.configs.recommended,
  pluginVue.configs['flat/essential'],

  globalIgnores([
    'vendor/*',
    '_build/*',
    'cypress_cache/*',
    'public/assets/*',
    'public/vendor/*',
    'resources/js/angular/*',
    '**/__mocks__/**',
  ]),

  {
    files: ['**/*.{js,mjs,cjs,vue}'],
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      'indent': ['error', 2],
      'vue/no-v-html': 'off',
      'vue/require-v-for-key': 'off',
      'eqeqeq': ['error', 'always'],
      'arrow-spacing': ['error'],
      'block-spacing': ['error'],
      'brace-style': ['error', 'stroustrup'],
      'comma-dangle': ['error', 'always-multiline'],
      'curly' : ['error'],
      'default-param-last': ['error'],
      'eol-last': ['error'],
      'keyword-spacing': ['error', {'before': true, 'after': true}],
      'space-before-blocks': ['error', 'always'],
      'linebreak-style': ['error', 'unix'],
      'no-trailing-spaces': ['error'],
      'no-var': ['error'],
      'prefer-arrow-callback': ['error'],
      'prefer-const': ['error'],
      'prefer-template': ['error'],
      'quotes': ['error', 'single', {'avoidEscape': true}],
      'semi': ['error', 'always'],
      'semi-style': ['error', 'last'],
      'template-curly-spacing': ['error', 'never'],
    },
  },
  {
    files: ['**/cypress/**/*.js'],
    ...pluginCypress.configs.recommended,
  },
  {
    files: ['**/*.spec.js'],
    plugins: {
      jest: pluginJest,
    },
    languageOptions: {
      globals: {
        ...globals.node,
        ...pluginJest.environments.globals.globals,
      },
    },
  },
  {
    files: [
      '**/postcss.config.js',
      '**/babel.config.js',
      '**/jest.config.js',
    ],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },
  {
    files: [
      '**/webpack.mix.js',
      '**/tailwind.config.js',
      '**/cypress.config.js',
    ],
    languageOptions: {
      sourceType: 'commonjs',
      globals: {
        ...globals.node,
      },
    },
  }
]);
