describe('viewTestPagination', () => {

  it('display all tests', () => {
    cy.clearCookies();
    cy.visit('index.php?project=Trilinos&date=2011-07-22');
    cy.get('tbody').find('td').contains('a', 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV').click();
    cy.url().should('contain', 'index.php?project=Trilinos&parentid=12');

    // first, verify the expected number of builds
    cy.get('#numbuilds').should('contain', 'Number of SubProjects: 36');
    cy.get('span.buildnums').should('contain', '36 builds');

    // check that only 10 builds are displayed at a time
    cy.get('tbody').find('tr').should('have.length', 10);

    // change pagination to display more items
    cy.contains('div', 'Items per page').find('select').select('50');
    cy.get('tbody').find('tr').should('have.length', 36);

    // verify that the 'Notes' link is shown for subproject builds on this page
    cy.get('tbody').find('tr td:nth-child(1)').each(subproj_td => {
      cy.wrap(subproj_td).find('a[name="notesLink"]').should('have.attr', 'href').and('match', /builds?\/[0-9]+\/notes/);
    });

    // next, click on a specific test
    cy.get('tbody').find('tr td:nth-child(7)').filter('.warning').as('builds_not_run');
    cy.get('@builds_not_run').should('have.length', 6);
    cy.get('@builds_not_run').first().should('contain', '29').click();

    cy.url().should('contain', 'viewTest.php?onlynotrun&buildid=');

    // make sure the expected number of tests are displayed
    cy.get('#viewTestTable').find('tbody').find('tr').should('have.length', 25);

    // now display all items
    cy.get('select[name="itemsPerPage"]').select('All');

    // make sure the expected number of tests are displayed
    cy.get('#viewTestTable').find('tbody').find('tr').should('have.length', 29);
  });

});
