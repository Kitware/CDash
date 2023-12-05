module.exports = {
  extends: [
    // add more generic rulesets here
    'plugin:vue/recommended',
  ],
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
  ignorePatterns: [
    '**/*.min.js',
  ],
};
