describe("viewSubProjects", function() {

  it("navigates between SubProjects", function() {
    browser.get('viewSubProjects.php?project=SubProjectExample');

    element(by.partialLinkText('ThreadPool')).click();
    browser.waitForAngular();

    browser.actions().mouseMove(element(by.linkText('Dashboard'))).perform();
    element(by.partialLinkText('SubProjects')).click();
    browser.waitForAngular();

    element(by.partialLinkText('NOX')).click();
    browser.waitForAngular();
  });

});
