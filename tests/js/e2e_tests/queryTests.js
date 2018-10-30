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

  it("filter on group", function() {
    filter_test("groupname", "61", "Experimental", 5);
    filter_test("groupname", "62", "Experimental", 0);
  });

  it("filter on site", function() {
    filter_test("site", "61", "CDashTestingSite", 5);
  });

  it("filter on time", function() {
    // This test should not count on all tests taking 0s
    // It should open the page with the time filter set to == 0
    // then make sure that all tests on the page have times
    // of 0s.
    // filter_test("time", "41", "0", 5);
  });

});
