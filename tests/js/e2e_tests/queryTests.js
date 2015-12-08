describe("queryTests", function() {
  it("show filters", function() {
    browser.get('queryTests.php?project=InsightExample');
    var link = element(by.id('label_showfilters'));
    link.click();
  });


  it("filter on build name", function() {
    element(by.id('id_field1')).$('[value="buildname"]').click();
    element(by.id('id_compare1')).$('[value="63"]').click();
    element(by.id('id_value1')).sendKeys('simple');
    element(by.name('apply')).click();
    browser.waitForAngular();
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(3);
  });

  it("filter on build time", function() {
    element(by.name('clear')).click();
    browser.waitForAngular();
    element(by.id('id_field1')).$('[value="buildstarttime"]').click();
    element(by.id('id_compare1')).$('[value="83"]').click();
    element(by.id('id_value1')).sendKeys('yesterday');
    element(by.name('apply')).click();
    browser.waitForAngular();
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(5);
  });

  it("filter on details", function() {
    element(by.name('clear')).click();
    browser.waitForAngular();
    element(by.id('id_field1')).$('[value="details"]').click();
    element(by.id('id_compare1')).$('[value="61"]').click();
    element(by.id('id_value1')).sendKeys('Completed');
    element(by.name('apply')).click();
    browser.waitForAngular();
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(5);
  });

  it("filter on site", function() {
    element(by.name('clear')).click();
    browser.waitForAngular();
    element(by.id('id_field1')).$('[value="site"]').click();
    element(by.id('id_compare1')).$('[value="61"]').click();
    element(by.id('id_value1')).sendKeys('CDashTestingSite');
    element(by.name('apply')).click();
    browser.waitForAngular();
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(5);
  });

  it("filter on time", function() {
    element(by.name('clear')).click();
    browser.waitForAngular();
    element(by.id('id_field1')).$('[value="time"]').click();
    element(by.id('id_compare1')).$('[value="41"]').click();
    element(by.id('id_value1')).sendKeys('0');
    element(by.name('apply')).click();
    browser.waitForAngular();
    expect(element.all(by.repeater('build in cdash.builds')).count()).toBe(5);
  });
});
