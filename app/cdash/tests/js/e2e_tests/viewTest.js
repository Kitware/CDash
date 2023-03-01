require('../pages/catchConsoleErrors.page.js');
describe("viewTest", function() {

  it("shows the test we expect", function() {
    browser.get('viewTest.php?buildid=1');
    expect(browser.getPageSource()).toContain("kwsys.testHashSTL");
  });

  it("shows link to queryTests.php", function() {
    browser.get('viewTest.php?buildid=1');
    browser.actions().mouseMove(element(by.linkText('Dashboard'))).perform();
    var link = element(by.linkText('Tests Query'));
    expect(link.getAttribute('href')).toContain('&filtercount=1&showfilters=1&field1=status&compare1=62&value1=Passed');
  });

  describe("Missing Tests", function() {

    beforeEach(async function() {
      browser.get('index.php?project=EmailProjectExample&date=2009-02-26');
      var href = await element(by.cssContainingText('a','Win32-MSVC2009')).getAttribute('href');
      var matches = href.match(/\/build\/([0-9]+)/);
      var buildid = matches[1];
      browser.get('viewTest.php?buildid=' + buildid);
    });

      it("should display missing tests", function() {
          var parser1Test1 = element(by.cssContainingText('tr','Parser1Test1'));
          var dashboardSendTest = element(by.cssContainingText('tr','DashboardSendTest'));
          var systemInfoTest = element(by.cssContainingText('tr','SystemInfoTest'));

          expect(parser1Test1.getText()).toEqual('Parser1Test1 Missing');
          expect(dashboardSendTest.getText()).toEqual('DashboardSendTest Missing');
          expect(systemInfoTest.getText()).toEqual('SystemInfoTest Missing');
      });

    it("should indicate the number of tests missing when filter not present in query", function(done){
      browser
        .driver
        .getCurrentUrl()
        .then(function (url) {
          var buildId, newPath, h3;
          var path = url.substr(url.indexOf('?')+1, url.length);

          path.split('&').forEach(function(param){
            var keyValue = param.split('=');
            if (keyValue[0] === 'buildid') {
              buildId = keyValue[1];
            }
          });

          newPath = 'viewTest.php?buildid=' + buildId;
          browser.get(newPath);

          h3 = element(by.id('test-totals-indicator'));
          expect(h3.getText()).toEqual('2 passed, 3 failed, 0 not run, 3 missing.');
          done();
        });
    });
  })
});
