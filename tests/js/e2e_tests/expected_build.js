var LoginPage = require('../pages/login.page.js');
describe("expected_build", function() {
  it("toggle expected", function() {
    var loginPage = new LoginPage();
    loginPage.login();
    browser.get('index.php?project=InsightExample');

    // Locate the folder icon
    var folderIcon = element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).element(by.name('adminoptions'));

    // Make sure that we located the right img.
    expect(folderIcon.getAttribute('src')).toContain('img/folder.png');

    // Click the icon to expand the menu.
    folderIcon.click();

    // Find the 'mark as expected' link and click it.
    var link = element(by.partialLinkText('mark as expected'));
    link.click();
    browser.waitForAngular();

    // Refresh the page to make sure this build is now expected.
    browser.get('index.php?project=InsightExample');
    element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).element(by.name('adminoptions')).click();
    expect(element(by.partialLinkText('mark as non expected')).isPresent()).toBe(true);

    // Make it non expected again.
    link = element(by.partialLinkText('mark as non expected'));
    link.click();
    browser.waitForAngular();

    // Refresh & verify.
    browser.get('index.php?project=InsightExample');
    element(by.repeater('build in buildgroup.pagination.filteredBuilds').row(0)).element(by.name('adminoptions')).click();
    expect(element(by.partialLinkText('mark as expected')).isPresent()).toBe(true);
  });
});
