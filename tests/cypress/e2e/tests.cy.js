describe('the test page', () => {
  it('can be reached from the viewTest page', () => {
    // navigate to viewTest
    cy.visit('index.php?project=TimeStatus');
    cy.get('#main_content').find('table').find('tbody').find('tr').eq(0).find('td').eq(4).find('a').click();
    // find the link to the test page and click it
    cy.get('#viewTestTable').find('tbody').find('tr').eq(0).find('td').eq(0).find('a').click();
    // make sure we're really on the test page
    cy.get('#subheadername').should('contain', 'TimeStatus').and('contain', 'Test Results');
  });


  it('can be reached from the queryTests page', () => {
    cy.visit('queryTests.php?project=TimeStatus&date=2018-01-25');
    // find the link to the test page and click it
    cy.get('#queryTestsTable').find('tbody').find('tr').eq(0).find('td').eq(3).find('a').click();
    // make sure we're really on the test page
    cy.get('#subheadername').should('contain', 'TimeStatus').and('contain', 'Test Results');
  });


  it('can be reached from the testSummary page', () => {
    cy.visit('testSummary.php?project=28&name=nap&date=2018-01-25');
    // find the link to the test page and click it
    cy.get('#testSummaryTable').find('tbody').find('tr').eq(0).find('td').eq(3).find('a').click();
    // make sure we're really on the test page
    cy.get('#subheadername').should('contain', 'TimeStatus').and('contain', 'Test Results');
  });


  it('loads the right navigation links', () => {
    cy.visit('testSummary.php?project=28&name=nap&date=2018-01-25');
    cy.get('#testSummaryTable').find('tbody').find('tr').eq(2).find('td').eq(3).as('test_td');
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

      // test that the 'up' button works
      cy.get('#Back').find('a').click();
      cy.url().should('include', 'viewTest.php');
    });
  });


  it('displays information about the test', () => {
    cy.visit('testSummary.php?project=28&name=nap&date=2018-01-25');
    cy.get('#testSummaryTable').find('tbody').find('tr').eq(2).find('td').eq(3).click();

    // verify information for the test we clicked on

    // duration of this test
    cy.get('#executiontime').find('span.builddateelapsed').should('contain', '9s');
    // test name
    cy.get('a#summary_link').should('contain', 'nap');
    // link to test summary page
    cy.get('a#summary_link').invoke('attr', 'href').should('contain', 'testSummary.php?project=28&name=nap&date=2018-01-25');
    // build name this test belongs to
    cy.get('a#build_link').should('contain', 'test_timing');
    // link to corresponding build page
    cy.get('a#build_link').invoke('attr', 'href').should('match', /builds?\/[0-9]+/);
    // site name this test ran from
    cy.get('a#site_link').should('contain', '(elysium)');
    // link to corresponding site page
    cy.get('a#site_link').invoke('attr', 'href').should('match', /sites?\/[0-9]+/);

    // general info displayed on the page
    cy.get('#main_content')
      .should('contain', 'on 2018-01-25 17:25:19')
      .and('contain', 'Completed')
      .and('contain', 'Warning')
      .and('contain', 'This test took longer to complete (9s) than the threshold allows (5s 120ms).');

    // expand the test command line
    cy.get('a#commandlinelink').should('contain', 'Show Command Line').click();
    cy.get('a#commandlinelink').should('contain', 'Hide Command Line');
    cy.get('pre#commandline').should('contain', '/a/path/to/test/nap --run-test .');
    // toggle it back
    cy.get('a#commandlinelink').click().should('contain', 'Show Command Line');

    // verify the test output field
    cy.get('pre#test_output').should('contain', 'PASS');
  });


  it('loads the "Test Time" and "Failing/Passing" graphs', () => {
    cy.visit('testSummary.php?project=28&name=nap&date=2018-01-25');
    cy.get('#testSummaryTable').find('tbody').find('tr').eq(2).find('td').eq(3).click();

    cy.on('uncaught:exception', (err, runnable) => {
      // FIXME: we catch this because rendering the graphs throws a console
      //   error and it's not worth fixing before we convert to d3
      if (err.message.includes('e.mousewheel is not a function')) {
        // return false to not fail the test in this case
        return false;
      }
    });

    // test the default value of the graph selection dropdown
    cy.get('select#GraphSelection').as('dropdown').contains('option', 'Select...').should('be.selected');

    // toggle the Test Time graph
    cy.get('@dropdown').select('Test Time');
    // TODO: (sbelsk) test this graph more thoroughly once it's in d3
    cy.get('#graph_holder').find('canvas').should('exist');
    // check export as JSON (clicking this just opens the API response in a new tab)
    cy.contains('a', 'View Graph Data as JSON')
      .invoke('attr', 'href')
      .should('match', /api\/v1\/testGraph.php\?testid=[0-9]+&buildid=[0-9]+&type=time/);

    // do the same for the Failing/Passing graph
    cy.get('@dropdown').contains('option', 'Test Time').should('be.selected');
    cy.get('@dropdown').select('Failing/Passing');
    cy.get('#graph_holder').find('canvas').should('exist');
    cy.contains('a', 'View Graph Data as JSON')
      .invoke('attr', 'href')
      .should('match', /api\/v1\/testGraph.php\?testid=[0-9]+&buildid=[0-9]+&type=status/);

    // toggle back to hide the graph
    cy.get('@dropdown').select('Select...');
    cy.get('#graph_holder').should('be.hidden');
  });
});
