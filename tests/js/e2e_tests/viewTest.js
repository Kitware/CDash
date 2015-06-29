describe("viewTest", function() {

  it("shows the test we expect", function() {
    browser.get('viewTest.php?buildid=1');
    expect(browser.getPageSource()).toContain("kwsys.testHashSTL");
  });

});
