var LoginPage = require('../pages/login.page.js');

describe("manageOverview", function() {
  it("is protected by login", function () {
    var loginPage = new LoginPage();
    loginPage.login("manageOverview.php?projectid=5");
  });

  it("can manage overview", function() {
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
