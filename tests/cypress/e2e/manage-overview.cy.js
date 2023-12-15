describe('manageOverview', () => {
  it('is protected by login', () => {
    cy.visit('manageOverview.php?projectid=5');

    // TODO: Use this once the page is converted to Vue
    // cy.url().should('eq', '/login');

    cy.get('#subheadername').contains('Login');
  });

  it('can manage overview', () => {
    cy.login();
    cy.visit('manageOverview.php?projectid=5');

    // Add a column for Experimental.
    cy.get('#newBuildColumn').select('Experimental');
    cy.get('#addBuildColumn').click();
    cy.get('#saveLayout').click();

    // Navigate to the overview page.
    cy.contains('Go to overview').click();

    // Make sure we have a coverage entry (from the 'simple' tests).
    // One row for the header row, and one for the coverage entry.
    cy.get('[data-cy="coverage-table"]').find('tr').should('have.length', 2);
  });
});
