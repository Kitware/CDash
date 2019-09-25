var LoginPage = require('../pages/login.page.js');
const EC = protractor.ExpectedConditions;
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

  it("removes begin/end from URI", function() {
    browser.get('index.php?project=InsightExample&begin=yesterday&end=today&filtercount=0&showfilters=1');

    // Click the Calendar link.
    element(by.partialLinkText('Calendar')).click();

    // Click on the current testing day.
    element(by.className('ui-datepicker-today')).click();

    // Verify that the resulting URL contains a date param with no begin or end.
    var d = new Date();
    var month = '' + (d.getMonth() + 1);
    var day = '' + d.getDate();
    var year = d.getFullYear();
    if (month.length < 2) {
      month = '0' + month;
    }
    if (day.length < 2) {
      day = '0' + day;
    }
    var date = [year, month, day].join('-');
    browser.wait(EC.urlContains('index.php?project=InsightExample&date=' + date + '&filtercount=0&showfilters=1'), 5000);
  });
});
