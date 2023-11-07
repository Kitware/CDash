var LoginPage = require('../pages/login.page.js');
require('../pages/catchConsoleErrors.page.js');

describe("manageOverview", function() {
  it("can manage overview", function() {
    var loginPage = new LoginPage();
    loginPage.login();

    browser.get('manageOverview.php?projectid=5');

    // Add a column for Experimental.
    element(by.id('newBuildColumn')).element(by.cssContainingText('option', 'Experimental')).click();
    element(by.id('addBuildColumn')).click();
    element(by.id('saveLayout')).click();

    // Navigate to the overview page.
    element(by.linkText('Go to overview')).click();

    // Make sure we have a coverage entry (from the 'simple' tests).
    expect(element.all(by.repeater('coverage in cdash.coverages')).count()).toBe(1);
  });
});
