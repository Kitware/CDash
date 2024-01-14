describe('manageBuildGroup', () => {

  it('is protected by login', () => {
    cy.visit('manageBuildGroup.php?projectid=5');
    cy.get('#subheadername').contains('Login');
  });


  it('can create Daily and Latest buildgroups', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');

    // create a Daily group
    cy.get('a').contains('Create new BuildGroup').click();
    cy.get('input[name="newBuildGroupName"]').type('aNewBuildGroup');
    cy.get('select[name="newBuildGroupType"]').find('option').contains('Daily').should('be.selected');
    cy.get('button').contains('Create BuildGroup').click();
    cy.get('#buildgroup_created').should('be.visible');

    // create a Latest group
    cy.get('input[name="newBuildGroupName"]').clear();
    cy.get('input[name="newBuildGroupName"]').type('latestBuildGroup');
    cy.get('select[name="newBuildGroupType"]').select('Latest');
    cy.get('button').contains('Create BuildGroup').click();
    cy.get('#buildgroup_created').should('be.visible');

    // make sure they're both on our list of current buildgroups
    cy.get('a').contains('Current BuildGroups').click();
    cy.get('#current').should('contain', 'aNewBuildGroup');
    cy.get('#current').should('contain', 'latestBuildGroup');
  });


  it('prevents creating duplicate buildgroups', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');

    // attempt to create a duplicate buildgroup
    cy.get('a').contains('Create new BuildGroup').click();
    cy.get('input[name="newBuildGroupName"]').type('aNewBuildGroup');
    cy.get('button').contains('Create BuildGroup').click();

    // validate error message
    cy.get('#create_group_error').should('contain', 'A group named \'aNewBuildGroup\' already exists for this project.');
  });


  it('can navigate between projects', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');

    // switch project from the drop down at the top of the page
    cy.get('select[name="projectSelection"]').select('TestHistory');
    cy.url().should('contain', 'manageBuildGroup.php?projectid=15');

    // switch back to original project
    cy.get('select[name="projectSelection"]').select('InsightExample');
    cy.url().should('contain', 'manageBuildGroup.php?projectid=5');
  });


  it('can modify a buildgroup', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');

    // select the 4th buildgroup in the list & expand its details
    cy.get('#sortable').find('div.row').eq(3).as('build_group');
    cy.get('@build_group').should('contain', 'aNewBuildGroup');
    cy.get('@build_group').find('span.glyphicon-chevron-right').click();

    // fill out the form & submit it
    cy.get('@build_group').find('form').as('build_group_form');

    // TODO: (sbelsk) cypress can't see the value bounded by Angular.
    //       Uncomment this once this page is reimplemented in Vue.
    //cy.get('@build_group_form').find('input[name="name"]').should('contain', 'aNewBuildGroup');

    // cy.get('@build_group_form').find('input[name="description"]').should('contain', '');
    cy.get('@build_group_form').find('input[name="description"]').type('temporary BuildGroup for testing');

    // cy.get('@build_group_form').find('input[name="autoremovetimeframe"]').should('contain', '0');
    cy.get('@build_group_form').find('input[name="autoremovetimeframe"]').clear();
    cy.get('@build_group_form').find('input[name="autoremovetimeframe"]').type('1');

    cy.get('@build_group_form').find('input[name="summaryEmail"][value="0"]').should('be.checked');
    cy.get('@build_group_form').find('input[name="summaryEmail"][value="1"]').should('not.be.checked');
    cy.get('@build_group_form').find('input[name="summaryEmail"][value="2"]').should('not.be.checked');
    cy.get('@build_group_form').find('input[name="summaryEmail"][value="2"]').check();

    cy.get('@build_group_form').find('input[name="emailCommitters"]').should('not.be.checked');
    cy.get('@build_group_form').find('input[name="emailCommitters"]').check();

    cy.get('@build_group_form').find('input[name="includeInSummary"]').should('be.checked');
    cy.get('@build_group_form').find('input[name="includeInSummary"]').uncheck();

    cy.get('@build_group_form').find('button[type="submit"]').click();

    // verify that our changes went through successfully
    cy.reload();
    // TODO: (sbelsk) uncomment these once this page is converted to Vue
    // cy.get('@build_group_form').find('input[name="name"]').should('contain', 'aNewBuildGroup');
    // cy.get('@build_group_form').find('input[name="description"]').should('contain', 'temporary BuildGroup for testing');
    // cy.get('@build_group_form').find('input[name="autoremovetimeframe"]').should('contain', '1');
    cy.get('@build_group_form').find('input[name="summaryEmail"][value="0"]').should('not.be.checked');
    cy.get('@build_group_form').find('input[name="summaryEmail"][value="1"]').should('not.be.checked');
    cy.get('@build_group_form').find('input[name="summaryEmail"][value="2"]').should('be.checked');
    cy.get('@build_group_form').find('input[name="emailCommitters"]').should('be.checked');
    cy.get('@build_group_form').find('input[name="includeInSummary"]').should('not.be.checked');
  });


  it('can create and delete wildcard rules', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');

    // fill out the wildcard rule form & submit it
    cy.get('a').contains('Wildcard BuildGroups').click();
    cy.get('select[name="wildcardBuildGroupSelection"]').select('aNewBuildGroup');
    // cy.get('input[name="wildcardBuildNameMatch"]').should('contain', ''); // TODO: (sbelsk) uncomment once in Vue
    cy.get('input[name="wildcardBuildNameMatch"]').clear();
    cy.get('input[name="wildcardBuildNameMatch"]').type('simple');
    cy.get('select[name="buildType"]').select('Experimental');
    cy.get('button[type="submit"]').contains('Define BuildGroup').click();

    // verify that our rule appears correctly after we refresh the page
    cy.reload();
    cy.get('a').contains('Wildcard BuildGroups').click();
    cy.get('div[name="existingwildcardrules"]').find('table').find('tbody').find('tr').first().find('td').as('wildcardFields');
    cy.get('@wildcardFields').eq(0).should('contain', 'aNewBuildGroup');
    cy.get('@wildcardFields').eq(1).should('contain', 'simple');
    cy.get('@wildcardFields').eq(2).should('contain', 'Experimental');

    // find the delete icon and click it
    cy.get('@wildcardFields').eq(3).find('span.glyphicon-trash').click();

    // make sure the wildcard rule isn't displayed on the page anymore
    cy.reload();
    cy.get('a').contains('Wildcard BuildGroups').click();
    cy.get('div[name="existingwildcardrules"]').should('not.exist');
  });


  it('can define and undefine dynamic rows', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');
    cy.get('a').contains('Dynamic BuildGroups').click();
    cy.get('select[name="dynamicSelection"]').select('latestBuildGroup');

    // fill out the form & submit it
    cy.get('select[name="parentBuildGroupSelection"]').select('Experimental');
    cy.get('select[name="siteSelection"]').select('CDashTestingSite');
    // cy.get('input[name="dynamicBuildNameMatch"]').should('contain', ''); // TODO: (sbelsk) uncomment once in Vue
    cy.get('input[name="dynamicBuildNameMatch"]').clear();
    cy.get('input[name="dynamicBuildNameMatch"]').type('CDash-CTest-sameImage');
    cy.get('button[type="submit"]').contains('Add content to BuildGroup').click();

    // fill out and submit again
    cy.get('select[name="parentBuildGroupSelection"]').select('Continuous');
    cy.get('select[name="siteSelection"]').select('Any');
    cy.get('input[name="dynamicBuildNameMatch"]').clear();
    cy.get('button[type="submit"]').contains('Add content to BuildGroup').click();

    // verify that the "latestBuildGroup" table has exactly two rows
    cy.reload();
    cy.get('a').contains('Dynamic BuildGroups').click();
    cy.get('select[name="dynamicSelection"]').select('latestBuildGroup');
    cy.get('div[name="existingdynamicrows"]').find('table').find('tbody').find('tr').as('dynamic_rows');
    cy.get('@dynamic_rows').should('have.length', 2);
    cy.get('@dynamic_rows').eq(0).find('td').eq(0).should('contain', 'Experimental');
    cy.get('@dynamic_rows').eq(0).find('td').eq(1).should('contain', 'CDashTestingSite');
    cy.get('@dynamic_rows').eq(0).find('td').eq(2).should('contain', 'CDash-CTest-sameImage');
    cy.get('@dynamic_rows').eq(1).find('td').eq(0).should('contain', 'Continuous');
    cy.get('@dynamic_rows').eq(1).find('td').eq(1).should('contain', 'Any');
    cy.get('@dynamic_rows').eq(1).find('td').eq(2).should('contain', '');

    // click on the delete icons and verify that no rows are present
    cy.get('@dynamic_rows').find('span.glyphicon-trash').as('delete_icons');
    cy.get('@delete_icons').should('have.length', 2);
    cy.get('@delete_icons').click({ multiple: true });

    // reload the page to make sure they're really gone
    cy.reload();
    cy.get('a').contains('Dynamic BuildGroups').click();
    cy.get('select[name="dynamicSelection"]').select('latestBuildGroup');
    cy.get('div[name="existingdynamicrows"]').should('not.exist');
  });


  it('can delete buildgroups', () => {
    cy.login();
    cy.visit('manageBuildGroup.php?projectid=5');

    function deleteBuildGroup(buildGroupName) {
      // select the buildgroup & expand its details
      cy.get('#sortable').find('div.row').contains(buildGroupName).as('build_group');
      cy.get('@build_group').find('span.glyphicon-chevron-right').click();

      // locate the deletion icon for this buildgroup and click it
      cy.get('@build_group').find('span.glyphicon-trash').click();
      cy.get('button#modal-delete-group-button').click();

      // make sure that this BuildGroup doesn't appear on the page anymore
      cy.get('#sortable').find('div.row').contains(buildGroupName).should('not.exist');

      // reload the page to make sure it's really gone from the database too
      cy.reload();
      cy.get('#sortable').find('div.row').contains(buildGroupName).should('not.exist');
    }

    deleteBuildGroup('aNewBuildGroup');
    deleteBuildGroup('latestBuildGroup');
  });

});
