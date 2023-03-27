module.exports = {
  verbose: true,
  moduleFileExtensions: ['js', 'vue'],
  testEnvironment: "jsdom",
  transform: {
    "^.+\\.js$": "babel-jest",
    "^.+\\.vue$": "@vue/vue2-jest",
  },
};
