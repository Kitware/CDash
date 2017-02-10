describe("viewTest", function() {

  it("shows the test we expect", function() {
    browser.get('viewTest.php?buildid=1');
    expect(browser.getPageSource()).toContain("kwsys.testHashSTL");
  });

  describe("Missing Tests", function() {

    beforeEach(function(){
      browser.get('index.php?project=EmailProjectExample');
      element(by.cssContainingText('a','Win32-MSVC2009')).click();
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

          newPath = `viewTest.php?buildid=${buildId}`;
          browser.get(newPath);

          h3 = element(by.id('test-totals-indicator'));
          expect(h3.getText()).toEqual('2 passed, 3 failed, 0 timed out, 0 not run, 3 missing.');
          done();
        });
    });
  })
});
