describe("viewBuildError", function() {

  it("shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=6');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("deltan shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=6&onlydeltan=1');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("deltap shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=6&onlydeltap=1');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("type=1 shows 10 warnings", function() {
    browser.get('viewBuildError.php?buildid=6&type=1');
    expect(browser.getPageSource()).toContain("10</b> Warnings");
  });
});
