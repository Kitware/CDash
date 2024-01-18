describe('viewTest', () => {

  it('shows the test we expect', () => {
    cy.visit('viewTest.php?buildid=1');
    cy.get('#viewTestTable').find('tbody').contains('tr', 'kwsys.testHashSTL').should('exist');
  });


  it('shows link to queryTests.php', () => {
    cy.visit('viewTest.php?buildid=1');

    const default_filters = 'queryTests.php?project=TestCompressionExample&date=2009-12-18&filtercount=1&showfilters=1&field1=status&compare1=62&value1=Passed';
    cy.get('#navigation').find('a').contains('Tests Query').should('have.attr', 'href').and('contains', default_filters);
  });


  it('accounts for missing tests', () => {
    // go to the viewTest page corresponding to build with name 'Win32-MSVC2009'
    cy.visit('index.php?project=EmailProjectExample&date=2009-02-26');
    cy.get('tbody').contains('a', 'Win32-MSVC2009').invoke('attr', 'href').then(build_url => {
      const buildid = build_url.match(/build\/([0-9]+)/)[1];
      cy.visit(`viewTest.php?buildid=${buildid}`);
    });

    // verify that summary table displays tests as missing
    function verify_missing_row(test_name) {
      cy.get('#viewTestTable').contains('tr', test_name).as('missing_test_row');
      cy.get('@missing_test_row').find('td').eq(0).should('contain', test_name);
      cy.get('@missing_test_row').find('td').eq(1).should('contain', 'Missing');
    }
    verify_missing_row('Parser1Test1');
    verify_missing_row('DashboardSendTest');
    verify_missing_row('SystemInfoTest');

    // check stats above the table
    function get_whitespace_regex(input_str) {
      const ws = '\\s+'; // matches any whitespace
      return new RegExp(ws + input_str.split(/\s+/).join(ws) + ws);
    }
    // TODO: (sbelsk) we have to do this hack to comapre the text
    //   because it's bound in angular and cypress can't get it easily.
    //   once this gets redone in Vue, we should simply do:
    //   cy.get('h3#test-totals-indicator').should('contain', '2 passed, 3 failed, 0 not run, 3 missing.');
    const expected_regex = get_whitespace_regex('2 passed, 3 failed, 0 not run, 3 missing.');
    cy.get('h3#test-totals-indicator').invoke('text').should('match', expected_regex);
  });

});
