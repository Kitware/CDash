var LoginPage = require('../pages/login.page.js');

describe("subProjectGroupOrder", function() {

  it("can change the group order", function() {
    var loginPage = new LoginPage();
    loginPage.login();
    browser.get('index.php?project=CrossSubProjectExample');

    // Hover over 'Settings' and click 'SubProjects'.
    browser.actions().mouseMove(element(by.linkText('Settings'))).perform();
    element(by.linkText('SubProjects')).click();

    // Navigate to the 'SubProjects Groups' tab.
    element(by.linkText('SubProject Groups')).click();

    // Move the Production group to the top of the list.
    var toMove = element(by.repeater('group in cdash.groups').row(2)).element(by.name('img_cell'));
    // And the position on-screen where we're gonna move it to.
    var position = element(by.repeater('group in cdash.groups').row(0)).element(by.name('img_cell')).getLocation();
    position.y -= 10;
    browser.actions().dragAndDrop(toMove, position).perform();
    element(by.buttonText('Save Order')).click();
    browser.waitForAngular();

    // Make sure it's still on the top after we refresh.
    browser.driver.navigate().refresh();
    expect(element(by.repeater('group in cdash.groups').row(0)).element(by.name('group_name')).getAttribute('value')).toBe('Production');
  });

  it("can verify SubProject group order", function() {
    // Navigate to our example of coverage across groups.
    browser.get('index.php?project=CrossSubProjectExample&date=2016-02-09');
    element(by.linkText('subproject_coverage_example')).click();

    // Make sure that Production is the first group listed after Total.
    expect(element(by.repeater('group in ::cdash.coveragegroups').row(1)).getInnerHtml()).toContain("Production");
  });
});
