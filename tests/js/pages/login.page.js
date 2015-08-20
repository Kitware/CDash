var LoginPage = function () {};

LoginPage.prototype = Object.create({}, {
  login: { value: function () {
    // Disable synchronization because login.php has not yet been
    // converted to AngularJs.
    browser.ignoreSynchronization = true;

    // Fill out the login form.
    browser.get('user.php');
    element(by.name('login')).sendKeys('simpletest@localhost');
    element(by.name('passwd')).sendKeys('simpletest');


    // Submit it and wait for the title to change.
    element(by.name('sent')).click().then(function () {
      browser.driver.wait(browser.driver.getTitle().then(function (title) {
        return title == "CDash - My Profile";
      }));
    });

    // Re-enable synchronization as we presumably return control to Angular.
    browser.ignoreSynchronization = false;
  }}
});

module.exports = LoginPage;
