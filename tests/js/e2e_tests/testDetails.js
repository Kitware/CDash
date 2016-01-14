describe("testDetails", function() {
  it("truncates output", function() {
    browser.get('testDetails.php?test=21&build=3');
    expect(browser.getPageSource()).toContain("The rest of the test output was removed since it exceeds the threshold of 1024 characters");
  });

  it("display test graphs", function() {
    browser.get('testDetails.php?test=21&build=3');
    var select = element(by.id('GraphSelection'));
    select.$('[value="TestTimeGraph"]').click();
    select.$('[value="TestPassingGraph"]').click();
  });
});
