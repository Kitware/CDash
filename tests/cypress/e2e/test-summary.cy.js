describe('testSummary', () => {
  // TODO: (sbelsk) the page shouldn't try to display stuff if the
  //  api call fails. Uncomment below whenever this is fixed...
  /*
  it('handles wrong or missing GET fields', () => {
    const invalid_urls = [
      'testSummary.php?project=3&date=2009-01-21',
      'testSummary.php?project=3&date=invalid-date',
      'testSummary.php?project=3&name=simple',
      'testSummary.php?project=3&name=noSuchTest',
      'testSummary.php?project=3',
      'testSummary.php?project=-999',
      'testSummary.php',
    ];
    invalid_urls.forEach((test_url) => {
      cy.request({url: test_url, failOnStatusCode: false}).its('status').should('equal', 400);
    });
  });
  */


  it('loads accurate information on the test', () => {
    cy.clearCookies();
    cy.visit('testSummary.php?project=15&name=flaky&date=2015-11-16');

    // assert expected text in the page headers
    const header_text = 'Testing summary for flaky performed between 2015-11-16T01:00:00 and 2015-11-17T01:00:00';
    cy.get('[data-cy="summary-header"]').should('contain', header_text);

    const stats_text = '40% passed, 3 failed out of 5.';
    cy.get('[data-cy="summary-stats"]').should('contain', stats_text);

    // the table in this page should be of size 6x6
    cy.get('[data-cy="summary-table"]').find('tr').should('have.length', 6);
    cy.get('[data-cy="summary-table"]').find('tr').each((row) => {
      cy.wrap(row).find('th,td').should('have.length', 6);
    });

    const get_cell = (row, col) => {
      return cy.get('[data-cy="summary-table"]').find('tr').eq(row).find('th,td').eq(col);
    };

    // assert the right stats are displayed in the table
    get_cell(0, 0).should('contain', 'Site');
    get_cell(0, 1).should('contain', 'Build Name');
    get_cell(0, 2).should('contain', 'Build Stamp');
    get_cell(0, 3).should('contain', 'Status');
    get_cell(0, 4).should('contain', 'Time (s)');
    get_cell(0, 5).should('contain', 'Build Revision');

    get_cell(1, 0).should('not.be.empty');
    get_cell(1, 1).should('contain', 'TestHistory');
    get_cell(1, 2).should('contain', '20151116-1904-Experimental');
    get_cell(1, 3).should('contain', 'Failed').and('have.class', 'error');
    get_cell(1, 4).should('contain', '0');

    get_cell(2, 0).should('not.be.empty');
    get_cell(2, 1).should('contain', 'TestHistory');
    get_cell(2, 2).should('contain', '20151116-1906-Experimental');
    get_cell(2, 3).should('contain', 'Failed').and('have.class', 'error');
    get_cell(2, 4).should('contain', '0');

    get_cell(4, 0).should('not.be.empty');
    get_cell(4, 1).should('contain', 'TestHistory');
    get_cell(4, 2).should('contain', '20151116-1905-Experimental');
    get_cell(4, 3).should('contain', 'Passed').and('have.class', 'normal');
    get_cell(4, 4).should('contain', '1');
  });


  it('can export the table as CSV', () => {
    cy.visit('testSummary.php?project=15&name=flaky&date=2015-11-16');

    // test export to CSV functionality
    cy.get('[data-cy="download-as-csv"]').click();

    const download_dir = Cypress.config('downloadsFolder');
    const expected_csv = new RegExp('^Site,Build Name,Build Stamp,Status,Time\\(s\\)\n(([^,]*,){5}\n){5}$');
    cy.readFile(`${download_dir}/test-export.csv`).should('match', expected_csv);
  });


  it('loads the right navigation links', () => {
    const base_url = 'testSummary.php?project=3&name=curl';
    cy.visit(`${base_url}&date=2009-02-23`);

    // click to previous day
    cy.get('li.btnprev').find('a').click();
    cy.url().should('include', `${base_url}&date=2009-02-22`);

    // navigate back by clicking 'next'
    cy.get('li.btnnext').find('a').click();
    cy.url().should('include', `${base_url}&date=2009-02-23`);

    // naviagte to next day
    cy.get('li.btnnext').find('a').click();
    cy.url().should('include', `${base_url}&date=2009-02-24`);

    // navigate to current day
    cy.get('li.btncurr').find('a').click();
    const today_str = new Date().toISOString().slice(0, 10);
    cy.url().should('include', `${base_url}&date=${today_str}`);
    cy.get('li.btnnext').find('a').should('not.exist');

    // test the 'up' button from the original page
    cy.visit(`${base_url}&date=2009-02-23`);
    cy.get('#Back').find('a').click();
    cy.url().should('include', 'index.php?project=EmailProjectExample&date=2009-02-23');
  });


  it('displays the test failure graph', () => {
    cy.visit('testSummary.php?project=3&name=curl&date=2009-02-23');
    const link = cy.get('[data-cy="toggle-plot"]');
    link.click();

    // Verify link text changes & graph appears.
    link.should('contain', 'Hide Test Failure Trend');
    cy.get('[data-cy="plot-wrapper"]').should('be.visible');

    cy.get('[data-cy="reset-plot"]').click();

    link.click();

    // Verify link text changes & graph appears.
    link.should('contain', 'Show Test Failure Trend');
    cy.get('[data-cy="plot-wrapper"]').should('not.be.visible');
  });
});
