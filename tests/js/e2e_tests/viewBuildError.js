describe("viewBuildError", function() {

  it("shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=7');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("deltan shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=7&onlydeltan=1');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("deltap shows 0 errors'", function() {
    browser.get('viewBuildError.php?buildid=7&onlydeltap=1');
    expect(browser.getPageSource()).toContain("0</b> Errors");
  });

  it("type=1 shows 3 warnings", function() {
    browser.get('viewBuildError.php?buildid=7&type=1');
    expect(browser.getPageSource()).toContain("3</b> Warnings");
  });
});
