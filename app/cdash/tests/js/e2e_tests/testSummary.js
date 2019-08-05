describe("testSummary", function() {
  it("display test failure graph", function() {
    browser.get('testSummary.php?project=3&name=curl&date=2009-02-23');
    var link = element(by.id('GraphLink'));
    link.click();

    // Verify link text changes & graph appears.
    expect(link.getText()).toContain('Hide Test Failure Trend');
    expect(element(by.id('TestFailureGraph')).isDisplayed()).toBeTruthy();

    element(by.linkText('Reset zoom')).click();

    link.click();

    // Verify link text changes & graph appears.
    expect(link.getText()).toContain('Show Test Failure Trend');
    expect(element(by.id('TestFailureGraph')).isDisplayed()).toBeFalsy();
  });
});
