describe("testDetails", function() {
  it("truncates output", function() {
    browser.get('testDetails.php?test=21&build=3');
    expect(browser.getPageSource()).toContain("The rest of the test output was removed since it exceeds the threshold of 1024 characters");
  });

  it("display test graphs", function() {
    browser.get('testDetails.php?test=21&build=3');
    var select = element(by.id('GraphSelection'));
    select.$('[value="time"]').click();
    select.$('[value="status"]').click();
  });

  it("colorizes test output", function() {
    var outputXpath = '//pre[@ng-bind-html="cdash.test.output | ctestNonXmlCharEscape | terminalColors | trustAsHtml"]';

    browser.get('index.php?project=OutputColor&date=2018-01-17');
    element(by.linkText('Linux-c++')).click();
    element(by.linkText('View Tests Summary')).click();
    element(by.linkText('colortest_short')).click();
    expect(element.all(by.xpath(outputXpath + '/span')).count()).toBe(3);
    // Make sure bold text is handled correctly by ansi-up v3+.
    expect(element.all(by.xpath(outputXpath + '/span')).get(0).getAttribute('style')).toBe('font-weight: bold;');

    browser.get('index.php?project=OutputColor&date=2018-01-17');
    element(by.linkText('Linux-c++')).click();
    element(by.linkText('View Tests Summary')).click();
    element(by.linkText('colortest_long')).click();
    expect(element.all(by.xpath(outputXpath + '/span')).count()).toBe(2);
    expect(element.all(by.xpath(outputXpath + '/script')).count()).toBe(0);
  });
});
