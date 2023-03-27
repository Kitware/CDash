require('../pages/catchConsoleErrors.page.js');
describe("viewUpdate", function() {
  it("can toggle activity graph", function() {
    // Navigate to viewUpdate.php for a particular build.
    browser.get('index.php?project=TestCompressionExample&date=2009-12-18');
    element(by.linkText('23a412')).click();

    // Verify links to source code repo are shown
    expect(element(by.linkText('23a41258921e1cba8581ee2fa5add00f817f39fe')).isDisplayed()).toBeTruthy();
    expect(element(by.linkText('0758f1dbf75d1f0a1759b5f2d0aa00b3aba0d8c4')).isDisplayed()).toBeTruthy();

    // Verify that we can show/hide the Activity Graph.
    element(by.linkText('Show Activity Graph')).click();
    expect(element(by.id('graph_holder')).isDisplayed()).toBeTruthy();
    element(by.linkText('Hide Activity Graph')).click();
    expect(element(by.id('graph_holder')).isDisplayed()).toBeFalsy();
  });
});
