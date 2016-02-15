describe("filterLabels", function() {

  it("pass filters to viewTest", function() {
    browser.get('index.php?project=Trilinos&date=2011-07-22');
    element(by.linkText('Windows_NT-MSVC10-SERIAL_DEBUG_DEV')).click();

    // First, verify the expected number of builds
    expect(element(by.id('numbuilds')).getText()).toBe('Number of SubProjects Built: 36');
    // Note: A maximum of 10 builds are displayed at a time
    expect(element.all(by.repeater('build in buildgroup.pagination.filteredBuilds')).count()).toBe(10);

    // Then apply our filter parameters.
    element(by.id('settings')).click();

    var link = element(by.id('label_showfilters'));
    link.click();

    element(by.id('id_field1')).$('[value="label"]').click();
    element(by.id('id_compare1')).$('[value="63"]').click();
    element(by.id('id_value1')).sendKeys('ra');
    element(by.name('apply')).click();

    // Wait for the page to load.
    browser.waitForAngular();

    // Make sure the expected number of builds are displayed
    expect(element(by.id('numbuilds')).getText()).toBe('Number of SubProjects Built: 5');
    expect(element.all(by.repeater('build in buildgroup.pagination.filteredBuilds')).count()).toBe(5);

    // Next, click on a specific test
    var build_failures = element.all(by.binding('build.test.fail'));
    expect(build_failures.count()).toBe(5);
    var test3 = build_failures.get(3);
    expect(test3.getText()).toBe('10');
    test3.click();
    browser.waitForAngular();

    // Make sure the expected number of tests are displayed
    expect(element.all(by.repeater('test in pagination.filteredTests')).count()).toBe(10);

  });

  it("pass filters to queryTests", function() {
    browser.get('index.php?project=Trilinos&date=2011-07-22');
    element(by.linkText('Windows_NT-MSVC10-SERIAL_DEBUG_DEV')).click();

    // First, verify the expected number of builds
    expect(element(by.id('numbuilds')).getText()).toBe('Number of SubProjects Built: 36');
    // Note: A maximum of 10 builds are displayed at a time
    expect(element.all(by.repeater('build in buildgroup.pagination.filteredBuilds')).count()).toBe(10);

    // Then apply our filter parameters.
    element(by.id('settings')).click();

    var link = element(by.id('label_showfilters'));
    link.click();

    element(by.id('id_field1')).$('[value="label"]').click();
    element(by.id('id_compare1')).$('[value="63"]').click();
    element(by.id('id_value1')).sendKeys('ra');
    element(by.name('apply')).click();

    // Wait for the page to load.
    browser.waitForAngular();

    // Make sure the expected number of builds are displayed
    expect(element(by.id('numbuilds')).getText()).toBe('Number of SubProjects Built: 5');
    expect(element.all(by.repeater('build in buildgroup.pagination.filteredBuilds')).count()).toBe(5);

    // Next, hover on 'Dashboard' and click on 'Tests Query'
    browser.actions().mouseMove(element(by.linkText('Dashboard'))).perform();
    element(by.linkText('Tests Query')).click();

    // Make sure the expected number of tests are displayed
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(69);

    // Click clear and wait for the page to reload.
    element(by.name('clear')).click();
    browser.waitForAngular();

    // Make sure the expected number of tests are displayed
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(200);
  });

});
