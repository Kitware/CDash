describe('manageSubProject', () => {

  it('is protected by login', () => {
    cy.visit('manageSubProject.php?projectid=8');
    cy.get('#subheadername').contains('Login');
  });


  // TODO: (sbelsk) add test to check that no subprojects under the
  // same parent project can be created with duplicate names


  it('can navigate between projects', () => {
    cy.login();
    cy.visit('manageSubProject.php?projectid=8');

    // switch project from the drop down at the top of the page
    cy.get('select[name="projectSelection"]').select('TestHistory');
    cy.url().should('contain', 'manageSubProject.php?projectid=14');

    // switch back to original project
    cy.get('select[name="projectSelection"]').select('Trilinos');
    cy.url().should('contain', 'manageSubProject.php?projectid=8');
  });


  it('can create subproject groups', () => {
    cy.login();
    cy.visit('manageSubProject.php?projectid=8');

    cy.get('a').contains('SubProject Groups').click();

    cy.get('input[name="newgroup"]').type('group1');
    cy.get('button').contains('Add group').click();

    cy.get('input[name="newgroup"]').clear();
    cy.get('input[name="newgroup"]').type('gorup2'); // intentional typo
    cy.get('button').contains('Add group').click();

    cy.reload();
    cy.get('a').contains('SubProject Groups').click();

    cy.get('table[data-cy="existing-subproject-groups"]').find('tbody').find('tr').as('rows');
    cy.get('@rows').should('have.length', 2);
    // TODO: (sbelsk) cypress can't see the values bounded by Angular in text
    //   inputs. Uncomment these lines once this page is reimplemented in Vue.
    // cy.get('@rows').eq(0).find('input[name="group_name"]').should('contain', 'group1');
    // cy.get('@rows').eq(1).find('input[name="group_name"]').should('contain', 'gorup2');
  });


  // TODO: (sbelsk) add test to check that no groups under the
  // same parent project can be created with duplicate names


  it('can modify a subproject group', () => {
    cy.login();
    cy.visit('manageSubProject.php?projectid=8');

    cy.get('a').contains('SubProject Groups').click();

    // change name to group2, change its threshold to 65,
    // and make it the default group
    cy.get('table[data-cy="existing-subproject-groups"]').find('tbody').find('tr').eq(1).as('row');

    cy.get('@row').find('input[name="group_name"]').clear();
    cy.get('@row').find('input[name="group_name"]').type('group2');
    cy.get('@row').find('input[name="groupRadio"]').check();
    cy.get('@row').find('input[name="coverage_threshold"]').clear();
    cy.get('@row').find('input[name="coverage_threshold"]').type('65');
    cy.get('@row').find('button').contains('Update').click();

    // verify these changes
    cy.reload();
    cy.get('a').contains('SubProject Groups').click();
    // TODO: (sbelsk) uncomment once in Vue.
    // cy.get('@row').find('input[name="group_name"]').should('contain', 'group2');
    // cy.get('@row').find('input[name="coverage_threshold"]').should('contain', '65');
    cy.get('@row').find('input[name="groupRadio"]').should('be.checked');
  });


  // TODO: (sbelsk) cypress cannot see the dropdown that this test targets
  //   because of its Angular binding. Uncomment once converted to Vue.
  /*
  it('can assign a subproject to a group', () => {
    cy.login();
    cy.visit('manageSubProject.php?projectid=8');

    // expand the details for our test subproject
    cy.get('[data-cy="subproject-item"]').contains('aNewSubProject').as('subproject');
    cy.get('@subproject').find('span.glyphicon-chevron-right').click();

    // find the dropdown menu and assign this subproject to "group1"
    cy.get('@subproject').find('select[name="groupid"]').as('change-group-dropdown');
    cy.get('@change-group-dropdown').select('group1');

    // reload the page to make sure this assignment stuck
    cy.reload();
    cy.get('@subproject').find('span.glyphicon-chevron-right').click();
    cy.get('@change-group-dropdown').find('option[label="group1"]').should('be.selected');
  });
  */


  it('can filter subprojects by group', () => {
    cy.login();
    cy.visit('manageSubProject.php?projectid=8');

    cy.get('select[name="groupSelection"]').select('group1');
    // TODO: (sbelsk) change the above expected number of filtered
    //   subprojects to 1 once the test before it is uncommented.
    cy.get('[data-cy="subproject-item"]').should('have.length', 0);
  });


  it('can delete subproject groups', () => {
    cy.login();
    cy.visit('manageSubProject.php?projectid=8');
    cy.get('a').contains('SubProject Groups').click();

    // click on the two 'delete group' icons
    // FIXME: (sbelsk) we have to search for the icons twice
    //   because the UI doesn't show the icon next to the
    //   default group unless it's the only existing one
    cy.get('table[data-cy="existing-subproject-groups"]').find('span.glyphicon-trash').click();
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000); // wait because the UI has a delay/fadeout on delete
    cy.get('table[data-cy="existing-subproject-groups"]').find('span.glyphicon-trash').click();

    // make sure the groups don't exist anymore
    cy.get('table[data-cy="existing-subproject-groups"]').should('not.exist');
    cy.reload();
    cy.get('a').contains('SubProject Groups').click();
    cy.get('table[data-cy="existing-subproject-groups"]').should('not.exist');
  });
});
