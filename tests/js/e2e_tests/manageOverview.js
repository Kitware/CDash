describe("manageOverview", function() {
  it("is protected by login", function () {
    browser.get('manageOverview.php?projectid=5');

    expect(element(by.name('email')));
    expect(element(by.name('password')));

    element(by.name('email')).sendKeys('simpletest@localhost');
    element(by.name('password')).sendKeys('simpletest');

    // Submit it and wait for the title to change.
    element(by.name('sent')).click().then(function () {
      browser.driver.wait(browser.driver.getTitle().then(function (title) {
        expect(title).toEqual("");
      }));
    });
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
