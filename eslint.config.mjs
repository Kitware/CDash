import js from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import pluginCypress from 'eslint-plugin-cypress';
import pluginJest from 'eslint-plugin-jest';
import stylistic from '@stylistic/eslint-plugin';
import { defineConfig, globalIgnores } from 'eslint/config';

export default defineConfig([
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],

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
    plugins: {
      '@stylistic': stylistic,
    },
    rules: {
      'eqeqeq': ['error', 'always'],
      'curly': 'error',
      'default-param-last': 'error',
      'no-var': 'error',
      'prefer-arrow-callback': 'error',
      'prefer-const': 'error',
      'prefer-template': 'error',

      '@stylistic/array-bracket-newline': ['error', 'consistent'],
      '@stylistic/array-bracket-spacing': 'error',
      '@stylistic/array-element-newline': ['error', 'consistent'],
      '@stylistic/arrow-parens': 'error',
      '@stylistic/arrow-spacing': 'error',
      '@stylistic/block-spacing': 'error',
      '@stylistic/brace-style': 'error',
      '@stylistic/comma-dangle': ['error', 'always-multiline'],
      '@stylistic/comma-spacing': 'error',
      '@stylistic/comma-style': 'error',
      '@stylistic/computed-property-spacing': 'error',
      '@stylistic/curly-newline': 'error',
      '@stylistic/dot-location': ['error', 'property'],
      '@stylistic/eol-last': 'error',
      '@stylistic/function-call-argument-newline': ['error', 'consistent'],
      '@stylistic/function-call-spacing': 'error',
      '@stylistic/function-paren-newline': ['error', 'multiline-arguments'],
      '@stylistic/generator-star-spacing': 'error',
      '@stylistic/implicit-arrow-linebreak': 'error',
      '@stylistic/indent': ['error', 2],
      '@stylistic/indent-binary-ops': ['error', 2],
      '@stylistic/key-spacing': 'error',
      '@stylistic/keyword-spacing': 'error',
      '@stylistic/lines-between-class-members': 'error',
      '@stylistic/max-statements-per-line': 'error',
      '@stylistic/member-delimiter-style': 'error',
      '@stylistic/new-parens': 'error',
      '@stylistic/no-extra-semi': 'error',
      '@stylistic/no-floating-decimal': 'error',
      '@stylistic/no-mixed-operators': 'error',
      '@stylistic/no-multi-spaces': 'error',
      '@stylistic/no-multiple-empty-lines': 'error',
      '@stylistic/no-tabs': 'error',
      '@stylistic/no-trailing-spaces': 'error',
      '@stylistic/no-whitespace-before-property': 'error',
      '@stylistic/object-curly-newline': 'error',
      '@stylistic/object-curly-spacing': ['error', 'always'],
      '@stylistic/space-before-blocks': ['error', 'always'],
      '@stylistic/linebreak-style': ['error', 'unix'],
      '@stylistic/quotes': ['error', 'single', {'avoidEscape': true}],
      '@stylistic/semi': ['error', 'always'],
      '@stylistic/semi-style': ['error', 'last'],
      '@stylistic/template-curly-spacing': ['error', 'never'],

      'vue/component-name-in-template-casing': ['error', 'PascalCase'],
      'vue/no-v-html': 'off',
      'vue/require-v-for-key': 'off',
      'vue/match-component-import-name': 'error',
      'vue/no-duplicate-class-names': 'error',
      'vue/no-empty-component-block': 'error',
      'vue/no-import-compiler-macros': 'error',
      'vue/no-ref-object-reactivity-loss': 'error',
      'vue/no-setup-props-reactivity-loss': 'error',
      'vue/no-root-v-if': 'error',
      'vue/no-template-target-blank': 'error',
      'vue/no-this-in-before-route-enter': 'error',
      'vue/no-undef-components': 'error',
      'vue/no-undef-directives': 'error',
      'vue/no-undef-properties': 'off', // Disabled until Apollo properties are addressed
      'vue/no-unsupported-features': 'error',
      'vue/no-unused-emit-declarations': 'error',
      'vue/no-unused-properties': 'error',
      'vue/no-unused-refs': 'error',
      'vue/no-use-v-else-with-v-for': 'error',
      'vue/no-useless-mustaches': 'error',
      'vue/no-useless-v-bind': 'error',
      'vue/padding-line-between-blocks': 'error',
      'vue/padding-lines-in-component-definition': ['error', {
        betweenOptions: 'always',
        withinOption: {
          apollo: 'ignore',
        },
        groupSingleLineProperties: true,
      }],
      'vue/prefer-prop-type-boolean-first': 'error',
      'vue/prefer-separate-static-class': 'error',
      'vue/prefer-single-event-payload': 'error',
      'vue/prefer-v-model': 'error',
      'vue/require-emit-validator': 'error',
      'vue/require-name-property': 'error',
      'vue/slot-name-casing': 'error',
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
  },
]);
