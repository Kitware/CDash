var LoginPage = require('../pages/login.page.js');
describe("calendar", function() {
  it("toggle calendar", function() {
    browser.get('index.php?project=InsightExample');

    // Find the 'Calendar' link and click it.
    var link = element(by.partialLinkText('Calendar'));
    link.click();

    // Verify calendar is displayed.
    expect(element(by.css('.hasDatepicker')).isDisplayed()).toBeTruthy();

    // Click again and verify that calendar is hidden.
    link.click();
    expect(element(by.css('.hasDatepicker')).isDisplayed()).toBeFalsy();
  });
});
