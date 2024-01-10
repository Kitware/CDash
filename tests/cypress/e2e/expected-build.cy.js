describe('expected_build', () => {
  beforeEach(() => {
    // cleanup before test in case previous run failed mid-way
    cy.login();
    cy.visit('index.php?project=InsightExample&date=2018-08-09');
    cy.get('#project_5_15').find('tbody').find('tr').first().find('img[name="adminoptions"]').click();
    cy.get('table.animate-show').find('tr').eq(2).then(row => {
      if (row.find('td:contains("mark as non expected")').length > 0) {
        row.find('a').click();
      }
    });
  });

  it('toggles expected mode for build and displays it in future table', () => {
    cy.visit('index.php?project=InsightExample');
    cy.get('#project_5_15').find('tr').last().find('td').eq(1).should('not.contain', 'test-build-relationships');

    // navigate to 'prev' page
    cy.get('a').contains('Prev').click();
    cy.url().should('contain', 'index.php?project=InsightExample&date=2018-08-09');

    // locate the folder icon
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').should('contain', 'test-build-relationships');
    cy.get('@build_td').find('img[name="adminoptions"]').as('folder_icon');

    // make sure that we located the right img
    cy.get('@folder_icon').should('have.attr', 'src', 'img/folder.png');
    cy.get('@folder_icon').click();

    // find the 'mark as expected' link and click it
    cy.get('@build_td').find('a').contains('mark as expected').click();

    // refresh the page to make sure this build is now expected
    cy.reload();
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();
    cy.get('@build_td').find('a').contains('mark as expected').should('not.exist');
    cy.get('@build_td').find('a').contains('mark as non expected').should('exist');

    // 'latest' should now display 'test-build-relationships' with unknown start time
    cy.get('a').contains('Latest').click();
    cy.get('#project_5_15').find('tr').last().should('contain', 'test-build-relationships');
    cy.get('#project_5_15').find('tr').last().find('td').last().should('contain', 'Expected build');

    // restore it to not be expected
    cy.get('a').contains('Prev').click();
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();
    cy.get('@build_td').find('a').contains('mark as non expected').click();

    // refresh & verify
    cy.reload();
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();
    cy.get('@build_td').find('a').contains('mark as non expected').should('not.exist');
    cy.get('@build_td').find('a').contains('mark as expected').should('exist');
  });
});
