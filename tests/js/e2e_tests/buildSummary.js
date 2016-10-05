var LoginPage = require('../pages/login.page.js');

describe("buildSummary", function() {

  function loadBuildSummary() {
    // Navigate to buildSummary page for a certain build.
    browser.get('index.php?project=EmailProjectExample&date=2009-02-23');
    element(by.linkText('Win32-MSVC2009')).click();
  }

  it("can toggle history graph", function() {
    loadBuildSummary();
    // Verify that we can show/hide the History table.
    element(by.linkText('Show Build History')).click();
    expect(element(by.id('historyGraph')).isDisplayed()).toBeTruthy();
    element(by.linkText('Show Build History')).click();
    expect(element(by.id('historyGraph')).isDisplayed()).toBeFalsy();
  });

  it("can toggle build graphs", function() {
    loadBuildSummary();
    // Verify that we can show/hide the various Build Graphs.
    var graphs = [
      {
        link: 'Show Build Time Graph',
        graph: 'buildtimegrapholder'
      },
      {
        link: 'Show Build Errors Graph',
        graph: 'builderrorsgrapholder'
      },
      {
        link: 'Show Build Warnings Graph',
        graph: 'buildwarningsgrapholder'
      },
      {
        link: 'Show Build Tests Failed Graph',
        graph: 'buildtestsfailedgrapholder'
      }
    ];
    for (var i = 0, len = graphs.length; i < len; i++) {
      element(by.linkText(graphs[i].link)).click();
      expect(element(by.id(graphs[i].graph)).isDisplayed()).toBeTruthy();
      element(by.linkText(graphs[i].link)).click();
      expect(element(by.id(graphs[i].graph)).isDisplayed()).toBeFalsy();
    }
  });

  it("can add a build note", function() {
    var loginPage = new LoginPage();
    loginPage.login();
    loadBuildSummary();

    // Record how many notes there are before we add a new one.
    var prevNumNotes = element.all(by.repeater('note in cdash.notes')).count();

    // Add a note
    element(by.linkText('Add a Note to this Build')).click();
    element(by.name('TextNote')).sendKeys('This is a note');
    element(by.buttonText('Add Note')).click();

    // Make sure the number of notes increased by one.
    var numNotes = element.all(by.repeater('note in cdash.notes')).count();
    expect(numNotes == prevNumNotes + 1);

    // Reload the page and make sure the number of notes does not change.
    loadBuildSummary();
    var numNotes2 = element.all(by.repeater('note in cdash.notes')).count();
    expect(numNotes == numNotes2);
  });

});
