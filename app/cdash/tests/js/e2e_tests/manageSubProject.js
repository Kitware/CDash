var LoginPage = require('../pages/login.page.js');

describe("manageSubProject", function() {

  it("is protected by login", function () {
    var loginPage = new LoginPage();
    loginPage.login("manageSubProject.php?projectid=8#/add");
  });

  it("can add a subproject", function() {

    browser.get('manageSubProject.php?projectid=8#/add');
    element(by.name('newsubproject')).sendKeys('aaaNewSubProject');
    element(by.buttonText('Add SubProject')).click();

    browser.get('manageSubProject.php?projectid=8#/current');
    expect(element(by.id('current')).getText()).toContain("aaaNewSubProject");
  });

  it("can add a dependency", function() {
    // Get the first subproject & expand its details.
    browser.get('manageSubProject.php?projectid=8');
    var subproject = element(by.repeater('subproject in cdash.subprojects').row(0));
    subproject.element(by.className('glyphicon-chevron-right')).click();

    // Find the dependency selection menu & wait for it to become visible.
    var select = subproject.element(By.className('dependency_selector'));
    select.isDisplayed().then(function () {

      // Select a new dependency and add it to our subproject.
      select.element(by.cssContainingText('option', 'Aristos')).click();
      subproject.element(By.className('btn-default')).click();

      // Verify that Aristos now appears in the list of dependencies
      // for this subproject.
      var found = subproject.all(by.repeater('dep in details.dependencies')).reduce(function(acc, elem) {
        return elem.getText().then(function(text) {
          return acc || (text == "- Aristos");
        });
      }, false);

    expect(found).toBe(true);
    });
  });

  it("can remove a dependency", function() {
    // Get the first subproject & expand its details.
    browser.get('manageSubProject.php?projectid=8');
    var subproject = element(by.repeater('subproject in cdash.subprojects').row(0));
    subproject.element(by.className('glyphicon-chevron-right')).click();

    // Locate the deletion icon for this dependency & wait for it to
    // become visible.
    var deleteIcon = subproject.all(by.className('glyphicon-trash')).get(1);
    deleteIcon.isDisplayed().then(function () {
      // Click on it.
      deleteIcon.click();
      browser.waitForAngular();

      // Verify that it no longer depends on Aristos.
      var found = subproject.all(by.repeater('dep in details.dependencies')).reduce(function(acc, elem) {
        return elem.getText().then(function(text) {
          return acc || (text == "- Aristos");
        });
      }, false);

    expect(found).toBe(false);
    });
  });


  it("can create subproject groups", function() {
    browser.get('manageSubProject.php?projectid=8#/groups');

    element(by.name('newgroup')).sendKeys('group1');
    element(by.name('isdefault')).click();
    element(by.buttonText('Add group')).click();

    element(by.name('newgroup')).clear();
    element(by.name('newgroup')).sendKeys('gorup2'); // intentional typo.
    element(by.buttonText('Add group')).click();

    browser.get('manageSubProject.php?projectid=8#/groups');
    var rows = element(by.tagName('tbody')).all(by.tagName('tr'));
    expect(rows.get(0).element(by.name("group_name")).getAttribute("value")).toBe("group1");
    expect(rows.get(1).element(by.name("group_name")).getAttribute("value")).toBe("gorup2");
  });


  it("can modify a subproject group", function() {
    browser.get('manageSubProject.php?projectid=8#/groups');

    // Change name to group2, change its threshold to 65,
    // and make it the default group.
    var row = element(by.tagName('tbody')).all(by.tagName('tr')).get(1);

    row.element(by.name("group_name")).clear();
    row.element(by.name("group_name")).sendKeys("group2");
    row.element(by.name("groupRadio")).click();
    row.element(by.name("coverage_threshold")).clear();
    row.element(by.name("coverage_threshold")).sendKeys("65");

    row.element(by.buttonText('Update')).click();

    // Verify these changes.
    browser.get('manageSubProject.php?projectid=8#/groups');
    row = element(by.tagName('tbody')).all(by.tagName('tr')).get(1);
    expect(row.element(by.name("group_name")).getAttribute("value")).toBe("group2");
    expect(row.element(by.name("coverage_threshold")).getAttribute("value")).toBe("65");
    expect(row.element(by.name("groupRadio")).getAttribute('checked')).toBeTruthy();
  });


  it("can assign a subproject to a group", function() {
    // Expand the details for our test subproject.
    browser.get('manageSubProject.php?projectid=8#/current');
    var subproject = element(by.repeater('subproject in cdash.subprojects').row(0));
    subproject.element(by.className('glyphicon-chevron-right')).click();

    // Find the group selection menu & wait for it to become visible.
    var select = subproject.element(By.className('subproject_group'));
    select.isDisplayed().then(function () {
      // Assign this subproject to 'group1';
      select.element(by.cssContainingText('option', 'group1')).click();
    });

    // Reload the page to make sure this assignment stuck.
    browser.get('manageSubProject.php?projectid=8#/current');
    subproject = element(by.repeater('subproject in cdash.subprojects').row(0));
    subproject.element(by.className('glyphicon-chevron-right')).click();
    select = subproject.element(By.className('subproject_group'));
    select.isDisplayed().then(function () {
      expect(select.$('option:checked').getText()).toBe('group1');
    });
  });


  it("can filter subprojects by group", function() {
    browser.get('manageSubProject.php?projectid=8#/current');
    element(by.name("groupSelection")).element(by.cssContainingText('option', 'group1')).click();
    expect(element.all(by.repeater('subproject in cdash.subprojects')).count()).toBe(1);
  });


  it("can delete subproject groups", function() {
    browser.get('manageSubProject.php?projectid=8#/groups');

    // Click on the first visible "delete group" icon twice.
    element(by.className('table-striped')).element(by.className('glyphicon-trash')).click();
    element(by.className('table-striped')).element(by.className('glyphicon-trash')).click();

    // Make sure our groups don't exist anymore.
    expect(element(by.id('groups')).getText()).not.toContain("group1");
    expect(element(by.id('groups')).getText()).not.toContain("group2");
    browser.get('manageSubProject.php?projectid=8#/groups');
    expect(element(by.id('groups')).getText()).not.toContain("group1");
    expect(element(by.id('groups')).getText()).not.toContain("group2");
  });


  it("can delete a subproject", function() {
    browser.get('manageSubProject.php?projectid=8');
    // Select the first subproject in the list & expand its details.
    var subproject = element(by.repeater('subproject in cdash.subprojects').row(0));
    subproject.element(by.className('glyphicon-chevron-right')).click();

    // Locate the deletion icon for this subproject & wait for it to
    // become visible.
    var deleteIcon = subproject.all(by.className('glyphicon-trash')).get(0);
    deleteIcon.isDisplayed().then(function () {

      // Click on it and make sure that 'aaaNewSubProject' doesn't appear
      // on the page anymore.
      deleteIcon.click();
      browser.waitForAngular();
      expect(element(by.id('current')).getText()).not.toContain("aaaNewSubProject");

      // Reload the page to make sure it's really gone from the database too.
      browser.get('manageSubProject.php?projectid=8');
      expect(element(by.id('current')).getText()).not.toContain("aaaNewSubProject");
    });
  });

});
