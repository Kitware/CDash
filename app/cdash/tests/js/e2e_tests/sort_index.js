describe("sort_index", function() {

  function sort_test(field, column_index, first_value, last_value) {
    browser.get('index.php?project=InsightExample&date=2010-07-07');

    // Mitigate default sorting by clicking on some other header first.
    var different_index = 0;
    if (column_index == 0) {
      different_index = 1;
    }
    var different_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(different_index);
    different_header.click();

    // Make sure this is the header we think it is.
    var header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(column_index);
    expect(header.getText()).toBe(field);

    // Click the header to sort by the specified field.
    header.click();

    // Make sure the sort indicator has the class we expect.
    expect(header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Filter out the table cells that aren't currently displayed.
    var visible_tds = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });

    // Check that the expected value is at the top of the list.
    expect(visible_tds.get(column_index).getText()).toBe(first_value);

    // Reverse order & check values again.
    header.click();
    expect(header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");
    expect(visible_tds.get(column_index).getText()).toBe(last_value);

    // Reload the page and make sure we get the same result.
    // This tests that our cookies are set & read correctly.
    browser.get('index.php?project=InsightExample&date=2010-07-07');
    expect(visible_tds.get(column_index).getText()).toBe(last_value);

    // Delete the cookie.
    // browser.manage().deleteAllCookies();
    browser.manage().deleteCookie('cdash_InsightExample_indexNightly_sort');
  }

  it("sort by Site", function() {
    sort_test('Site', 0, 'thurmite.kitware', 'dash13.kitware');
  });

  it("sort by Build Name", function() {
    sort_test('Build Name', 1, 'zApps-Win32-vs60', 'zApp-Win64-Vista-vs9-Release');
  });

  it("sort by Updated Files", function() {
    sort_test('Files', 2, '4', '0');
  });

  it("sort by Configure Errors", function() {
    sort_test('Error', 3, '2', '0');
  });

  it("sort by Configure Warnings", function() {
    sort_test('Warn', 4, '2', '0');
  });

  it("sort by Build Errors", function() {
    sort_test('Error', 5, '2', '0');
  });

  it("sort by Build Warnings", function() {
    sort_test('Warn', 6, '3', '0');
  });

  it("sort by Test Not Run", function() {
    sort_test('Not Run', 7, '3', '1');
  });

  it("sort by Test Fail", function() {
    sort_test('Fail', 8, '3', '1');
  });

  it("sort by Test Pass", function() {
    sort_test('Pass', 9, '3', '1');
  });

  it("sort by Start Time", function() {
    sort_test('Start Time', 10, 'Jul 07, 2010 - 08:26 EDT', 'Jul 07, 2010 - 08:22 EDT');
  });

  it("sort by multiple columns", function() {
    browser.get('index.php?project=InsightExample&date=2010-07-07');

    // Clear default sorting by clicking on the Site header.
    var site_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(0);
    site_header.click();

    // Click on the Build Time header.
    var buildtime_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(10);
    expect(buildtime_header.getText()).toBe('Start Time');
    buildtime_header.click();
    expect(buildtime_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Then hold down shift and click on Test Pass header.
    var pass_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(9);

    browser.actions().mouseMove(pass_header).keyDown(protractor.Key.SHIFT).click().perform();
    expect(pass_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Filter out the table cells that aren't currently displayed.
    var visible_tds = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });

    // Check that the expected build is at the top of the list.
    expect(visible_tds.get(0).getText()).toBe('dash13.kitware');

    // Reverse order & check values again.
    pass_header.click();
    //browser.actions().mouseMove(pass_header).keyDown(protractor.Key.SHIFT).click().perform();
    expect(pass_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");
    expect(visible_tds.get(0).getText()).toBe('thurmite.kitware');
  });


});
