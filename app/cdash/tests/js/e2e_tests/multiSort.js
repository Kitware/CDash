require('../pages/catchConsoleErrors.page.js');
describe("multiSort", function() {

  function check_build_order(first_value, second_value, third_value) {
    // Filter out the table cells that aren't currently displayed.
    var visible_tds_row0 = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });
    var visible_tds_row1 = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(1)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });
    var visible_tds_row2 = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(2)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });

    expect(visible_tds_row0.get(0).getText()).toBe(first_value);
    expect(visible_tds_row1.get(0).getText()).toBe(second_value);
    expect(visible_tds_row2.get(0).getText()).toBe(third_value);

    // Delete the cookie.
    browser.manage().deleteAllCookies();
  }

  it("sort by configure warnings", function() {
    browser.get('index.php?date=20110722&project=Trilinos');

    // Clear default sorting by clicking on the Site header.
    var site_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(0);
    site_header.click();

    // Check that the builds are in the expected order
    check_build_order('test.kitware', 'hut12.kitware', 'hut11.kitware');

    // Click on the Configure Warnings header.
    var config_warn_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(4);
    expect(config_warn_header.getText()).toBe('Warn');
    config_warn_header.click();
    expect(config_warn_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Check that the builds are in the expected order
    check_build_order('hut11.kitware', 'hut12.kitware', 'test.kitware');

    // Then hold down shift and click on Test Fail header.
    var testfail_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(8);
    expect(testfail_header.getText()).toBe('Fail');
    browser.actions().mouseMove(testfail_header).keyDown(protractor.Key.SHIFT).click().perform();
    expect(testfail_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Configure warnings should still be sorted
    expect(config_warn_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Check that the builds are in the (same) expected order
    check_build_order('hut11.kitware', 'hut12.kitware', 'test.kitware');

    // Now try clicking on 'Configure Warn' again to reverse order
    config_warn_header.click();
    expect(config_warn_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");

    // Test Fail should still be sorted
    expect(testfail_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Check that the builds are in the expected order
    check_build_order('test.kitware', 'hut12.kitware', 'hut11.kitware');

    // Click on Test Fail without the SHIFT key
    browser.actions().mouseMove(testfail_header).keyUp(protractor.Key.SHIFT).click().perform();
    expect(testfail_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");

    // Configure warnings should not be sorted
    expect(config_warn_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-none");

    // Check that the builds are in the expected order
    check_build_order('hut11.kitware', 'hut12.kitware', 'test.kitware');

  });

  it("sort by time columns", function() {
    browser.get('index.php?date=20110722&project=Trilinos');

    // Display advanced settings
    element(by.id('settings')).click();

    var link = element(by.id('label_advancedview'));
    link.click();

    // Clear default sorting by clicking on the Site header.
    var site_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(0);
    site_header.click();

    // Click on the Build Time header.
    var buildtime_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(9);
    expect(buildtime_header.getText()).toBe('Time');
    buildtime_header.click();
    expect(buildtime_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Check that the builds are in the expected order
    check_build_order('hut11.kitware', 'test.kitware', 'hut12.kitware');

    // Click on the Build Time header again to reverse order.
    buildtime_header.click();
    expect(buildtime_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");

    // Check that the builds are in the expected order
    check_build_order('test.kitware', 'hut12.kitware', 'hut11.kitware');

    // Then hold down shift and click on Configure Time header.
    var configuretime_header = element.all(by.className('table-heading')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(6);
    expect(configuretime_header.getText()).toBe('Time');
    browser.actions().mouseMove(configuretime_header).keyDown(protractor.Key.SHIFT).click().perform();
    expect(configuretime_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // build time should still be sorted
    expect(buildtime_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");

    // Check that the builds are in the expected order
    check_build_order('hut12.kitware', 'test.kitware', 'hut11.kitware');
  });

  it("sort Query Tests", function() {
    browser.get('queryTests.php?project=Trilinos&date=2011-07-22');

    // Clear default sorting by clicking on the Site header.
    var site_header = element.all(by.className('table-heading1')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(0);
    browser.actions().mouseMove(site_header).keyUp(protractor.Key.SHIFT).click().perform();

    // Click on the Status header.
    var status_header = element.all(by.className('table-heading1')).all(by.tagName('th')).filter(function(elem) { return elem.isDisplayed(); }).get(3);
    expect(status_header.getText()).toBe('Status');
    status_header.click();
    expect(status_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-down");

    // Check that the builds are in the expected order
    var visible_tds_row0 = element(by.repeater('build in pagination.filteredBuilds').row(0)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });
    expect(visible_tds_row0.get(3).getText()).toBe('Passed');

    var visible_tds_row24 = element(by.repeater('build in pagination.filteredBuilds').row(24)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });
    expect(visible_tds_row24.get(3).getText()).toBe('Passed');

    // Click on the Status header again.
    status_header.click();
    expect(status_header.element(by.tagName('span')).getAttribute('class')).toContain("glyphicon-chevron-up");

    // Check that the builds are in the expected order
    visible_tds_row0 = element(by.repeater('build in pagination.filteredBuilds').row(0)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });
    expect(visible_tds_row0.get(3).getText()).toBe('Failed');

    visible_tds_row24 = element(by.repeater('build in pagination.filteredBuilds').row(24)).all(by.tagName('td')).filter(function(elem) { return elem.isDisplayed(); });
    expect(visible_tds_row24.get(3).getText()).toBe('Failed');
  });

});
