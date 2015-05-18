var LoginPage = function () {};

LoginPage.prototype = Object.create({}, {
  login: { value: function () {
    // Disable synchronization because login.php has not yet been
    // converted to AngularJs.
    browser.ignoreSynchronization = true;

    browser.get('user.php');

    element(by.name('login')).sendKeys('simpletest@localhost');
    element(by.name('passwd')).sendKeys('simpletest');
    element(by.name('sent')).click();

    // Re-enable synchronization as we presumably return control to Angular.
    browser.ignoreSynchronization = false;
  }}
});

module.exports = LoginPage;
