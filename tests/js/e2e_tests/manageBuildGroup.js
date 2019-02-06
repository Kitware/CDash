var LoginPage = require('../pages/login.page.js');

describe("manageBuildGroup", function() {

  it("can create buildgroup", function() {
    var loginPage = new LoginPage();
    loginPage.login();
    browser.get('manageBuildGroup.php?projectid=5#/create');

    // Create a daily group
    element(by.name('newBuildGroupName')).sendKeys('aaNewBuildGroup');
    element(by.buttonText('Create BuildGroup')).click();

    // And a latest group
    element(by.name('newBuildGroupName')).clear();
    element(by.name('newBuildGroupName')).sendKeys('latestBuildGroup');
    element(by.name('newBuildGroupType')).element(by.cssContainingText('option', 'Latest')).click();
    element(by.buttonText('Create BuildGroup')).click();

    // Make sure they're both on our list of current BuildGroups.
    browser.get('manageBuildGroup.php?projectid=5#/current');
    expect(element(by.id('current')).getInnerHtml()).toContain("aaNewBuildGroup");
    expect(element(by.id('current')).getInnerHtml()).toContain("latestBuildGroup");
  });


  it("prevents duplicate buildgroups", function() {
    // Attempt to create a duplicate buildgroup
    browser.get('manageBuildGroup.php?projectid=5#/create');
    element(by.name('newBuildGroupName')).sendKeys('Experimental');
    element(by.buttonText('Create BuildGroup')).click();

    // Validate error message.
    expect(element(by.id('create_group_error')).getText()).toEqual(
      "A group named 'Experimental' already exists for this project.");
  });


  it("can modify a buildgroup", function() {
    browser.get('manageBuildGroup.php?projectid=5#/current');
    // Select the 4th buildgroup in the list & expand its details.
    var buildgroup = element(by.repeater('buildgroup in cdash.buildgroups').row(3));
    buildgroup.element(by.className('glyphicon-chevron-right')).click();

    // Locate one of our form inputs & wait for it to become visible.
    var descriptionField = buildgroup.element(by.name('description'));
    descriptionField.isDisplayed().then(function () {

      // Fill out the form & submit it.
      var buildgroup = element(by.repeater('buildgroup in cdash.buildgroups').row(3));
      var nameField = buildgroup.element(by.name('name'));
      nameField.clear();
      nameField.sendKeys('aaaNewBuildGroup');
      descriptionField.clear();
      descriptionField.sendKeys('temporary BuildGroup for testing');
      timeframeField = buildgroup.element(by.name('autoremovetimeframe'));
      timeframeField.clear();
      timeframeField.sendKeys('1');
      buildgroup.all(by.name('summaryEmail')).get(2).click();
      buildgroup.element(by.name('emailCommitters')).click();
      buildgroup.element(by.name('includeInSummary')).click();
      buildgroup.element(by.buttonText('Save')).click();
      browser.waitForAngular();

      // Verify that our changes went through successfully.
      browser.get('manageBuildGroup.php?projectid=5#/current');
      var buildgroup = element(by.repeater('buildgroup in cdash.buildgroups').row(3));
      expect(buildgroup.element(by.name("name")).getAttribute("value")).toBe("aaaNewBuildGroup");
      expect(buildgroup.element(by.name("description")).getAttribute("value")).toBe("temporary BuildGroup for testing");
      expect(buildgroup.element(by.name("autoremovetimeframe")).getAttribute("value")).toBe("1");
      expect(buildgroup.all(by.name('summaryEmail')).get(2).getAttribute('checked')).toBeTruthy();
      expect(buildgroup.element(by.name('emailCommitters')).getAttribute('checked')).toBeTruthy();
      expect(buildgroup.element(by.name('includeInSummary')).getAttribute('checked')).toBeFalsy();
    });
  });


  it("can create a wildcard rule", function() {
    // Fill out the wildcard rule form & submit it.
    browser.get('manageBuildGroup.php?projectid=5#/wildcard');
    element(by.name('wildcardBuildGroupSelection')).element(by.cssContainingText('option', 'aaaNewBuildGroup')).click();
    var matchField = element(by.name('wildcardBuildNameMatch'));
    matchField.clear();
    matchField.sendKeys('simple');
    element(by.name('buildType')).element(by.cssContainingText('option', 'Experimental')).click();
    element(by.buttonText('Define BuildGroup')).click();
    browser.waitForAngular();

    // Verify that our rule appears correctly after we refresh the page.
    browser.get('manageBuildGroup.php?projectid=5#/wildcard');
    var wildcardFields = element(by.repeater('wildcard in cdash.wildcards').row(0)).all(by.tagName('td'));
    expect(wildcardFields.get(0).getText()).toEqual("aaaNewBuildGroup");
    expect(wildcardFields.get(1).getText()).toEqual("simple");
    expect(wildcardFields.get(2).getText()).toEqual("Experimental");
  });


  it("can delete a wildcard rule", function() {
    browser.get('manageBuildGroup.php?projectid=5#/wildcard');

    // Find the delete icon and click it.
    var rule = element(by.repeater('wildcard in cdash.wildcards').row(0));
    var deleteIcon = rule.element(by.className('glyphicon-trash'));
    deleteIcon.click();
    browser.waitForAngular();

    // Make sure the wildcard rule isn't displayed on the page anymore.
    browser.get('manageBuildGroup.php?projectid=5#/wildcard');
    var ruleForm = element(by.name("existingwildcardrules"));
    expect(ruleForm.isPresent()).toBeFalsy();
  });


  it("can define dynamic rows", function() {
    // Fill out the form & submit it.
    browser.get('manageBuildGroup.php?projectid=5#/dynamic');
    element(by.name('dynamicSelection')).element(by.cssContainingText('option', 'latestBuildGroup')).click();
    element(by.name('parentBuildGroupSelection')).element(by.cssContainingText('option', 'Experimental')).click();
    var matchField = element(by.name('dynamicBuildNameMatch'));
    matchField.clear();
    matchField.sendKeys('same*mage');
    element(by.buttonText('Add content to BuildGroup')).click();
    browser.waitForAngular();

    element(by.name('dynamicSelection')).element(by.cssContainingText('option', 'latestBuildGroup')).click();
    element(by.name('parentBuildGroupSelection')).element(by.cssContainingText('option', 'Experimental')).click();
    matchField.clear();
    element(by.buttonText('Add content to BuildGroup')).click();
    browser.waitForAngular();
  });


  it("can verify dynamic rows", function() {
    // Find the "latestBuildGroup" table on this page and verify that it has
    // exactly two rows`.
    browser.get("index.php?project=InsightExample");
    expect(element(By.partialLinkText("latestBuildGroup")).element(by.xpath('../..')).all(by.repeater('build in buildgroup.pagination.filteredBuilds')).count()).toBe(2);
  });


  it("can delete dynamic rows", function() {
    // Select our dynamic group.
    browser.get('manageBuildGroup.php?projectid=5#/dynamic');
    element(by.name('dynamicSelection')).element(by.cssContainingText('option', 'latestBuildGroup')).click();

    // Wait for delete icons to appear.
    var deleteIcon = element(by.id('dynamic')).all(by.className('glyphicon-trash')).get(0);
    deleteIcon.isDisplayed().then(function () {
      // Click on them & verify that no rows are present.
      deleteIcon.click();
      browser.waitForAngular();
      deleteIcon = element(by.id('dynamic')).all(by.className('glyphicon-trash')).get(0);
      deleteIcon.click();
      browser.waitForAngular();
      expect(element(by.name("existingdynamicrows")).isPresent()).toBeFalsy();

      // Reload the page to make sure they're really gone.
      browser.get('manageBuildGroup.php?projectid=5#/dynamic');
      element(by.name('dynamicSelection')).element(by.cssContainingText('option', 'latestBuildGroup')).click();
      expect(element(by.name("existingdynamicrows")).isPresent()).toBeFalsy();
    });
  });


  it("can change buildgroup order", function() {
    browser.get('manageBuildGroup.php?projectid=5#/current');
    // The BuildGroup we're gonna move.
    var buildgroup = element(by.repeater('buildgroup in cdash.buildgroups').row(3));
    // And the position on-screen where we're gonna move it to.
    var position = element(by.repeater('buildgroup in cdash.buildgroups').row(0)).getLocation();
    position.y -= 10;

    browser.actions().dragAndDrop(buildgroup, position).perform();
    element(by.buttonText('Update Order')).click();
    browser.waitForAngular();

    // Make sure it's on the top after we reload.
    browser.get('manageBuildGroup.php?projectid=5#/current');
    expect(element(by.repeater('buildgroup in cdash.buildgroups').row(0)).getInnerHtml()).toContain("aaaNewBuildGroup");
  });


  it("can delete buildgroups", function() {
    function deleteBuildGroup(idx, buildGroupName) {
      browser.get('manageBuildGroup.php?projectid=5#/current');
      // Select the buildgroup & expand its details.
      var buildgroup = element(by.repeater('buildgroup in cdash.buildgroups').row(idx));
      buildgroup.element(by.className('glyphicon-chevron-right')).click();

      // Locate the deletion icon for this buildgroup & wait for it to
      // become visible.
      var deleteIcon = buildgroup.all(by.className('glyphicon-trash')).get(0);
      deleteIcon.isDisplayed().then(function () {

        // Click on it.
        deleteIcon.click();
        browser.wait(function() {
          "use strict";
          return element(by.id('modal-delete-group-button')).isPresent();
        });

        element(by.id('modal-delete-group-button')).click();

        // Make sure that this BuildGroup doesn't appear on the page anymore.
        browser.waitForAngular();
        expect(element(by.id('current')).getInnerHtml()).not.toContain(buildGroupName);

        // Reload the page to make sure it's really gone from the database too.
        browser.get('manageBuildGroup.php?projectid=5#/current');
        expect(element(by.id('current')).getInnerHtml()).not.toContain(buildGroupName);
      });
    }

    deleteBuildGroup(0, "aaaNewBuildGroup");
    deleteBuildGroup(3, "latestBuildGroup");
  });

});
