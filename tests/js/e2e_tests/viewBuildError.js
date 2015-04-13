describe("viewBuildError", function() {

  it("shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=8');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("deltan shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=8&onlydeltan=1');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("deltap shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=8&onlydeltap=1');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("type=1 shows 3 warnings", function() {
    browser.get('viewBuildError.php?buildid=8&type=1');
    expect(browser.getPageSource()).toContain("3</b> Warnings");
  });
});
