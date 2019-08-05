var LoginPage = require('../pages/login.page.js');
describe("remove_build", function() {
  it("remove build", function() {
    var loginPage = new LoginPage();
    loginPage.login();

    browser.get('index.php?project=InsightExample');

    // Locate the folder icon for the 2nd build.
    var folderIcon = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(1)).element(by.name('adminoptions'));

    // Make sure that we located the right img.
    expect(folderIcon.getAttribute('src')).toContain('img/folder.png');

    // Click the icon to expand the menu.
    folderIcon.click();

    // Find the 'remove this build' link and click it.
    var link = element(by.partialLinkText('remove this build'));
    link.click();

    // This generates a confirmation dialog which we have to accept.
    // Wait for it to appear.
    browser.wait(function() {
      "use strict";
      return element(by.id('modal-delete-build-button')).isPresent();
    });

    element(by.id('modal-delete-build-button')).click();
    browser.waitForAngular();

    // Refresh the page to make sure this build is gone now.
    browser.get('index.php?project=InsightExample');
    expect(element.all(by.repeater('build in buildgroup.pagination.filteredBuilds')).count()).toBe(4);
  });
});
