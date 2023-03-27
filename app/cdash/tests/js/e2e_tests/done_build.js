var LoginPage = require('../pages/login.page.js');
require('../pages/catchConsoleErrors.page.js');
describe("done_build", function() {

  function validate_test(index_url, link, old_text, new_text) {
    link.click();
    browser.waitForAngular();

    // Refresh the page to make sure this build's "doneness" was changed.
    browser.get(index_url);
    element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).element(by.name('adminoptions')).click();
    expect(element(by.partialLinkText(new_text)).isPresent()).toBe(true);

    // Toggle it back to its original state.
    link = element(by.partialLinkText(new_text));
    link.click();
    browser.waitForAngular();

    // Refresh & verify.
    browser.get(index_url);
    element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).element(by.name('adminoptions')).click();
    expect(element(by.partialLinkText(old_text)).isPresent()).toBe(true);
  }

  function toggle_done(index_url) {
    browser.get(index_url);

    // Locate the folder icon.
    var folderIcon = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).element(by.name('adminoptions'));

    // Make sure that we located the right img.
    expect(folderIcon.getAttribute('src')).toContain('img/folder.png');

    // Click the icon to expand the menu.
    folderIcon.click();

    // Find the 'mark as [not] done' link and click it.
    var link = element(by.partialLinkText('mark as done'));
    link.isPresent().then(function(result) {
      if (result) {
        validate_test(index_url, link, 'mark as done', 'mark as not done');
      } else {
        // Newer versions of CTest mark builds as done automatically.
        link = element(by.partialLinkText('mark as not done'));
        validate_test(index_url, link, 'mark as not done', 'mark as done');
      }
    });
  }

  it("toggle done for normal build", function() {
    var loginPage = new LoginPage();
    loginPage.login();
    toggle_done('index.php?project=InsightExample');
  });

  it("toggle done for parent build", function() {
    toggle_done('index.php?project=Trilinos&date=2011-07-22');
  });
});
