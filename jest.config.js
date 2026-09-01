module.exports = {
  verbose: true,
  moduleFileExtensions: ['js'],
  testEnvironment: 'jsdom',
  testEnvironmentOptions: {
    customExportConditions: ['node', 'node-addons'],
  },
  transform: {
    '^.+\\.js$': 'babel-jest',
  },
  transformIgnorePatterns: [
    '/node_modules/(?!echarts|zrender|jquery)/',
  ],
};
