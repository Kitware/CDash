describe('User profile page', () => {
  it('is protected by login', () => {
    cy.visit('/manageBanner.php');
    cy.url().should('eq', `${Cypress.config().baseUrl}/login`);
  });

  it('Can change banner', () => {
    cy.visit('/index.php?project=InsightExample');
    cy.get('[name=banner]').should('not.exist');

    cy.login();
    cy.visit('/manageBanner.php');
    cy.get('[name=message]').clear().type('this is a new banner');
    cy.get('[name=updateMessage]').click();

    cy.visit('/index.php?project=InsightExample');
    cy.get('[name=banner]').should('contain.text', 'this is a new banner');

    cy.visit('/manageBanner.php');
    cy.get('[name=message]').clear();
    cy.get('[name=updateMessage]').click();

    cy.visit('/index.php?project=InsightExample');
    cy.get('[name=banner]').should('not.exist');
  });
});
