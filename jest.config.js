module.exports = {
  verbose: true,
  moduleFileExtensions: ['js'],
  testEnvironment: 'node',
  transform: {
    '^.+\\.js$': 'babel-jest',
  },
  transformIgnorePatterns: [
    '/node_modules/(?!echarts|zrender|jquery)/',
  ],
};
