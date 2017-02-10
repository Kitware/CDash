describe("viewTest", function() {

  it("shows the test we expect", function() {
    browser.get('viewTest.php?buildid=1');
    expect(browser.getPageSource()).toContain("kwsys.testHashSTL");
  });

  describe("Missing Tests", function() {

    beforeEach(function(){
        /* browser.get('index.php?project=EmailProjectExample');
         element(by.cssContainingText('a','Win32-MSVC2009')).click(); */

        // Test number one cannot have a filtered applied via the query string
        // so for now this id needs to be hard coded
        browser.get('viewTest.php?buildid=98835');
    });

    it("should indicate the number of tests missing", function(){
      var h3 = element(by.id('test-totals-indicator'));

      expect(h3.getText()).toEqual('2 passed, 3 failed, 0 timed out, 0 not run, 3 missing.');
    });

    it("should display missing tests", function() {


      var parser1Test1 = element(by.cssContainingText('tr','Parser1Test1'));
      var dashboardSendTest = element(by.cssContainingText('tr','DashboardSendTest'));
      var systemInfoTest = element(by.cssContainingText('tr','SystemInfoTest'));

      expect(parser1Test1.getText()).toEqual('Parser1Test1 Missing');
      expect(dashboardSendTest.getText()).toEqual('DashboardSendTest Missing');
      expect(systemInfoTest.getText()).toEqual('SystemInfoTest Missing');
    });
  })

});
