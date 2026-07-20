describe('the test page', () => {
  it('can be reached from the queryTests page', () => {
    cy.visit('queryTests.php?project=TimeStatus&date=2018-01-25');
    // find the link to the test page and click it
    cy.get('#queryTestsTable').find('tbody').find('tr').eq(0).find('td').eq(3).find('a').click();
    // make sure we're really on the test page
    cy.get('#subheadername').should('contain', 'TimeStatus').and('contain', 'Test Results');
  });


  it('loads the right navigation links', () => {
    cy.visit('queryTests.php?project=TimeStatus&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=nap&date=2018-01-25');

    // Sort by build time deterministically.
    cy.get('#queryTestsTable').contains('Build Time').click();

    cy.get('#queryTestsTable').find('tbody').find('tr').eq(2).find('td').eq(3).as('test_td');

    cy.get('@test_td').find('a').invoke('attr', 'href').then(test_url => {
      const test_id = test_url.match(/tests?\/([0-9]+)/)[1];
      // visit a test page
      cy.visit(`tests/${test_id}`);

      // navigate to previous day, twice
      cy.get('#header-nav-previous-btn').find('a').click();
      cy.get('#header-nav-previous-btn').find('a').click();

      // now assert that we are at the oldest test
      cy.get('#header-nav-previous-btn').should('have.class', 'btn-disabled');

      // navigate back by clicking 'next'
      cy.get('#header-nav-next-btn').find('a').click();
      cy.get('#header-nav-next-btn').find('a').click();

      // navigate to current day
      cy.get('#header-nav-current-btn').find('a').click();
      cy.get('#header-nav-next-btn').should('have.class', 'btn-disabled');
    });
  });
});
