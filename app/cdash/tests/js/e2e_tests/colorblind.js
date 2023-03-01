require('../pages/catchConsoleErrors.page.js');
describe("colorblind", function() {
  it("toggle", function() {
    // Classic colors by default.
    browser.get('index.php?project=InsightExample&date=2010-07-07');
    expect(element.all(by.css('.normal')).first().getCssValue('background-color')).toEqual('rgba(180, 220, 180, 1)');
    // Toggle to colorblind colors.
    element(by.id('settings')).click();
    element(by.id('label_colorblind')).click();
    expect(element.all(by.css('.normal')).first().getCssValue('background-color')).toEqual('rgba(136, 179, 206, 1)');
  });
});
