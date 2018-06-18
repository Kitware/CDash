describe("queryTests", function() {
  function filter_test(field, compare, value, num_builds) {
    // Load the filtered page.
    browser.get('queryTests.php?project=InsightExample&filtercount=1&showfilters=1&field1=' + field + '&compare1=' + compare + '&value1=' + value);

    // Make sure the expected number of rows are displayed.
    expect(element.all(by.repeater('build in pagination.filteredBuilds')).count()).toBe(num_builds);
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
    filter_test("time", "41", "0", 5);
  });

});
