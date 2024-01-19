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

describe('site page', () => {
  it('can navigated to from the index page', () => {
    cy.visit('index.php?subproject=Teuchos&project=Trilinos');
    cy.get('tbody').contains('a', 'hut11.kitware').click();
    cy.url().should('contain', 'sites/');
  });

  it('loads the expected info for the site', () => {
    cy.visit('index.php?subproject=Teuchos&project=Trilinos');
    cy.get('tbody').contains('a', 'hut11.kitware').click();

    cy.get('#subheadername').should('contain', 'hut11.kitware');
    cy.get('#main_content')
      .should('contain', 'Processor Speed:')
      .and('contain', 'Processor Vendor:')
      .and('contain', 'Number of CPUs:')
      .and('contain', 'Number of Cores:')
      .and('contain', 'Total Physical Memory:')
      .and('contain', 'Description:')
      .and('contain', 'This site belongs to the following projects:');
    cy.get('#main_content').find('a').each(project_url => {
      cy.wrap(project_url).should('have.attr', 'href').and('contain', 'index.php?project=');
    });
  });

  it('loads the "Time spent" graph', () => {
    cy.visit('index.php?project=BatchmakeExample');
    cy.get('tbody').contains('a', 'Dash20.kitware').click();
    cy.get('#main_content').should('contain', 'Time spent per project');

    // TODO: (sbelsk) test this graph more thoroughly once it's in d3
    cy.get('#placeholder').find('canvas').should('exist');
  });
});
