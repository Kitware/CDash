var LoginPage = function () {};

LoginPage.prototype = Object.create({}, {
  login: { value: function (url = 'manageSubProject.php') {

    // Fill out the login form.
      browser.get(url);

      expect(element(by.name('email')));
      expect(element(by.name('password')));

      element(by.name('email')).sendKeys('simpletest@localhost');
      element(by.name('password')).sendKeys('simpletest');

      // Submit it and wait for the title to change.
      element(by.name('sent')).click().then(function () {
        browser.driver.wait(browser.driver.getTitle().then(function (title) {
          expect(title).not.toBeNull();
        }));
      });
  }}
});

module.exports = LoginPage;
