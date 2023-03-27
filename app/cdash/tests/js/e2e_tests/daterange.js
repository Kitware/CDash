require('../pages/catchConsoleErrors.page.js');
describe("date range selector", function() {
  const EC = protractor.ExpectedConditions;
  it("input boxes are empty by default and do not set date params", function() {
    browser.get('index.php?project=InsightExample&showfilters=1');
    expect(element(by.id('begin')).getAttribute('value')).toBe('');
    expect(element(by.id('end')).getAttribute('value')).toBe('');
    element(by.name('apply')).click();
    browser.wait(EC.urlContains('index.php?project=InsightExample&filtercount=1&showfilters=1&field1=site&compare1=63&value1='), 5000);
  });

  it("date param sets begin and end fields", function() {
    browser.get('index.php?project=InsightExample&date=2009-02-23&showfilters=1');
    expect(element(by.id('begin')).getAttribute('value')).toBe('2009-02-23');
    expect(element(by.id('end')).getAttribute('value')).toBe('2009-02-23');
  });

  it("only begin or end are set in the URL", function() {
    // begin
    browser.get('index.php?project=InsightExample&begin=2009-02-23&showfilters=1');
    expect(element(by.id('begin')).getAttribute('value')).toBe('2009-02-23');
    expect(element(by.id('end')).getAttribute('value')).toBe('2009-02-23');
    element(by.id('end')).clear();
    element(by.name('apply')).click();
    browser.wait(EC.urlContains('index.php?project=InsightExample&date=2009-02-23&filtercount=1&showfilters=1&field1=site&compare1=63&value1='), 5000);

    // end
    browser.get('index.php?project=InsightExample&end=2009-02-23&showfilters=1');
    expect(element(by.id('begin')).getAttribute('value')).toBe('2009-02-23');
    expect(element(by.id('end')).getAttribute('value')).toBe('2009-02-23');
    element(by.id('begin')).clear();
    element(by.name('apply')).click();
    browser.wait(EC.urlContains('index.php?project=InsightExample&date=2009-02-23&filtercount=1&showfilters=1&field1=site&compare1=63&value1='), 5000);
  });

  it("a range of dates can be selected", function() {
    browser.get('index.php?project=InsightExample&begin=2009-02-22&end=2009-02-24&showfilters=1');
    expect(element(by.id('begin')).getAttribute('value')).toBe('2009-02-22');
    expect(element(by.id('end')).getAttribute('value')).toBe('2009-02-24');
    element(by.name('apply')).click();
    browser.wait(EC.urlContains('index.php?project=InsightExample&begin=2009-02-22&end=2009-02-24&filtercount=1&showfilters=1&field1=site&compare1=63&value1='), 5000);
  });

  it("clearing begin and end removes date params", function() {
    browser.get('index.php?project=InsightExample&end=2009-02-23&showfilters=1');
    element(by.id('begin')).clear();
    element(by.id('end')).clear();
    element(by.name('apply')).click();
    browser.wait(EC.urlContains('index.php?project=InsightExample&filtercount=1&showfilters=1&field1=site&compare1=63&value1='), 5000);
  });
});
