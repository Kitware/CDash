describe('expected_build', () => {
  beforeEach(() => {
    // cleanup before test in case previous run failed mid-way
    cy.login();
    cy.visit('index.php?project=InsightExample&date=2018-08-09');
    cy.get('#project_5_15').find('tbody').find('tr').first().find('span[name="adminoptions"]').click();
    cy.get('table.animate-show').find('tr').eq(2).then(row => {
      if (row.find('button:contains("Mark as Non Expected")').length > 0) {
        row.find('button').click();
      }
    });
  });

  it('toggles expected mode for build and displays it in future table', () => {
    cy.visit('index.php?project=InsightExample');
    cy.get('#project_5_15').find('tr').last().find('td').eq(1).should('not.contain', 'test-build-relationships');

    // navigate to 'prev' page
    cy.get('a').contains('Prev').click();
    cy.url().should('contain', 'index.php?project=InsightExample&date=2018-08-09');

    // locate the admin options icon
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').should('contain', 'test-build-relationships');
    cy.get('@build_td').find('span[name="adminoptions"]').as('admin_icon');

    // make sure that we located the right icon
    cy.get('@admin_icon').should('have.class', 'glyphicon-cog');
    cy.get('@admin_icon').click();

    // find the 'Mark as Expected' button and click it
    cy.get('@build_td').find('button').contains('Mark as Expected').click();

    // refresh the page to make sure this build is now expected
    cy.reload();
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('span[name="adminoptions"]').click();
    cy.get('@build_td').find('button').contains('Mark as Expected').should('not.exist');
    cy.get('@build_td').find('button').contains('Mark as Non Expected').should('exist');

    // 'latest' should now display 'test-build-relationships' with unknown start time
    cy.get('a').contains('Latest').click();
    cy.get('#project_5_15').find('tr').last().should('contain', 'test-build-relationships');
    cy.get('#project_5_15').find('tr').last().find('td').last().should('contain', 'Expected build');

    // restore it to not be expected
    cy.get('a').contains('Prev').click();
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('span[name="adminoptions"]').click();
    cy.get('@build_td').find('button').contains('Mark as Non Expected').click();

    // refresh & verify
    cy.reload();
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('span[name="adminoptions"]').click();
    cy.get('@build_td').find('button').contains('Mark as Non Expected').should('not.exist');
    cy.get('@build_td').find('button').contains('Mark as Expected').should('exist');
  });

  it('batch marks multiple builds as expected and not expected', () => {
    // navigate to the page with builds
    cy.visit('index.php?project=InsightExample&date=2010-07-07');

    // enable bulk selection mode
    cy.contains('button', 'Bulk Select').click();
    cy.contains('button', 'Exit Selection').should('exist');

    // verify checkboxes are now visible
    cy.get('#project_5_13').find('tbody').find('tr').first().find('input[type="checkbox"]').should('exist');

    // count how many builds are available
    cy.get('#project_5_13').find('tbody').find('tr').its('length').then((rowCount) => {
      // select the first build (always exists)
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('input[type="checkbox"]').check();

      cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('input[type="checkbox"]').check();
      cy.contains(`${Math.min(2, rowCount)} build(s) selected`).should('be.visible');

      // click the "Mark as Expected" button in the bulk actions toolbar
      cy.contains('button', 'Mark as Expected').click();

      // wait for page reload
      cy.url().should('contain', 'index.php?project=InsightExample&date=2010-07-07');

      // verify first build is now expected
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('span[name="adminoptions"]').click();
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('table.animate-show').should('be.visible');
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('button').contains('Mark as Non Expected').should('exist');
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('button').contains('Mark as Expected').should('not.exist');
      // close admin options
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('span[name="adminoptions"]').click();

      // Check second build
      cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('span[name="adminoptions"]').click();
      cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('table.animate-show').should('be.visible');
      cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('button').contains('Mark as Non Expected').should('exist');
      cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('button').contains('Mark as Expected').should('not.exist');
      // close admin options
      cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('span[name="adminoptions"]').click();

      // re-enable bulk selection mode to mark them back as not expected
      cy.contains('button', 'Bulk Select').click();

      // select the same builds again
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('input[type="checkbox"]').check();
      if (rowCount > 1) {
        cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('input[type="checkbox"]').check();
      }

      // mark them as not expected
      cy.contains('button', 'Mark as Not Expected').click();

      // wait for page reload
      cy.url().should('contain', 'index.php?project=InsightExample&date=2010-07-07');

      // verify first build is now not expected
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('span[name="adminoptions"]').click();
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('table.animate-show').should('be.visible');
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('button').contains('Mark as Expected').should('exist');
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('button').contains('Mark as Non Expected').should('not.exist');
      // close admin options
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('span[name="adminoptions"]').click();

      // if there was a second build, verify it too
      if (rowCount > 1) {
        cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('span[name="adminoptions"]').click();
        cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('table.animate-show').should('be.visible');
        cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('button').contains('Mark as Expected').should('exist');
        cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('button').contains('Mark as Non Expected').should('not.exist');
        // close admin options
        cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('span[name="adminoptions"]').click();
      }

      // re-enable bulk selection mode to test clear selection
      cy.contains('button', 'Bulk Select').click();

      // test "Clear Selection" button
      cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('input[type="checkbox"]').check();
      cy.contains('1 build(s) selected').should('be.visible');
      cy.contains('button', 'Clear Selection').click();
      cy.contains('build(s) selected').should('not.exist');

      // exit selection mode
      cy.contains('button', 'Exit Selection').click();
      cy.contains('button', 'Bulk Select').should('exist');
      cy.get('#project_5_13').find('tbody').find('tr').first().find('input[type="checkbox"]').should('not.exist');
    });
  });
});
