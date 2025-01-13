describe('site statistics', () => {
  it('is protected by login', () => {
    cy.visit('/sites');
    cy.get('#subheadername').contains('Login');
  });

  it('can be reached from the user page', () => {
    cy.login();
    cy.visit('/user');
    cy.contains('a', 'Site Statistics').as('site_stats_url').scrollIntoView();
    cy.get('@site_stats_url').click();
    cy.url().should('contain', '/sites');
  });

  it('displays stats table', () => {
    cy.login();
    cy.visit('/sites');

    cy.get('#subheadername').should('contain', 'Site Statistics');
    cy.get('#siteStatisticsTable').find('th').eq(0).should('contain', 'Site Name');
    cy.get('#siteStatisticsTable').find('th').eq(1).should('contain', 'Busy time');
    cy.get('#siteStatisticsTable').find('tbody').find('tr').each(row => {
      cy.wrap(row).find('td').first().find('a').should('have.attr', 'href').and('match', /sites\/[0-9]+/);
    });
  });
});
