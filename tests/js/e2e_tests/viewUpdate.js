describe("viewUpdate", function() {
  it("can toggle activity graph", function() {
    // Navigate to viewUpdate.php for a particular build.
    browser.get('index.php?project=TestCompressionExample&date=2009-12-18');
    element(by.linkText('23a412')).click();

    // Verify that we can show/hide the Activity Graph.
    element(by.linkText('Show Activity Graph')).click();
    expect(element(by.id('graph_holder')).isDisplayed()).toBeTruthy();
    element(by.linkText('Hide Activity Graph')).click();
    expect(element(by.id('graph_holder')).isDisplayed()).toBeFalsy();
  });
});
