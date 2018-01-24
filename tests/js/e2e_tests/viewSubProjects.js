describe("viewSubProjects", function() {

  it("navigates between SubProjects", function() {
    browser.get('viewSubProjects.php?project=SubProjectExample');

    element(by.linkText('ThreadPool')).click();
    browser.sleep(2000);

    browser.actions().mouseMove(element(by.linkText('Dashboard'))).perform();
    element(by.linkText('SubProjects')).click();
    browser.sleep(2000);

    element(by.linkText('Teuchos')).click();
    browser.sleep(2000);
  });

});
