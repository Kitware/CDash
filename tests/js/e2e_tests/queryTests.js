describe("queryTests", function() {
  it("show filters", function() {
    browser.get('queryTests.php?project=InsightExample');
    var link = element(by.id('label_showfilters'));
    link.click();
  });

  function filter_test(field, compare, value, num_builds) {
    // Click clear and wait for the page to reload.
    element(by.name('clear')).click();
    return browser.driver.wait(function() {
      return browser.driver.getCurrentUrl().then(function(url) {
        return /filtercount=0/.test(url);
      });
    }, 10000);

    // Apply our filter parameters.
    element(by.id('id_field1')).$('[value="' + field + '"]').click();
    element(by.id('id_compare1')).$('[value="' + compare + '"]').click();
    element(by.id('id_value1')).sendKeys(value);
    element(by.name('apply')).click();

    // Wait for the page to load.
    var regexp = new RegExp(field);
    return browser.driver.wait(function() {
      return browser.driver.getCurrentUrl().then(function(url) {
        return regexp.test(url);
      });
    }, 10000);

    // Make sure the expected number of rows are displayed.
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(num_builds);
  }

  it("filter on build name", function() {
    filter_test("buildname", "63", "simple", 3);
  });

  it("filter on build time", function() {
    filter_test("buildstarttime", "83", "yesterday", 5);
  });

  it("filter on details", function() {
    filter_test("details", "61", "Completed", 5);
  });

  it("filter on site", function() {
    filter_test("site", "61", "CDashTestingSite", 5);
  });

  it("filter on time", function() {
    filter_test("site", "41", "0", 5);
  });
});
