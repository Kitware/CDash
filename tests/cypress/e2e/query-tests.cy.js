describe('query tests', () => {
  function filter_test(field, compare, value, num_builds) {
    // load the filtered page
    const filter_url = `queryTests.php?project=InsightExample&filtercount=1&field1=${field}&compare1=${compare}&value1=${value}`;
    cy.visit(filter_url);

    // make sure the expected number of rows are displayed
    cy.get('#numtests').should('contain', `Query  Tests: ${num_builds} matches`);
    cy.get('#queryTestsTable').find('tbody').find('tr').should('have.length', num_builds);
  }

  it('filters correctly by build name', () => {
    filter_test('buildname', '63', 'simple', 3);
  });


  it('filters correctly by build time', () => {
    filter_test('buildstarttime', '83', 'yesterday', 4);
  });

  it('filters correctly by details', () => {
    filter_test('details', '61', 'Completed', 4);
  });

  it('filters correctly by group', () => {
    filter_test('groupname', '61', 'Experimental', 4);
    filter_test('groupname', '62', 'Experimental', 0);
  });

  it('filters correctly by site', () => {
    filter_test('site', '61', 'CDashTestingSite', 4);
  });

  it('filters correctly by time', () => {
    // count all tests that took 0s to run
    filter_test('time', '41', '0', 4);

    // make sure all filtered tests actually have 'Time' equal to zero
    cy.get('#queryTestsTable').find('tbody').find('tr').each(row => {
      cy.wrap(row).find('td').eq(4).should('contain', '0s');
    });
  });

  it('displays the right default filters', () => {
    cy.visit('index.php?project=InsightExample');
    const today_str = new Date().toISOString().slice(0, 10);
    const default_filters = `queryTests.php?project=InsightExample&date=${today_str}&filtercount=1&showfilters=1&field1=status&compare1=62&value1=Passed`;
    cy.get('#navigation').find('a').contains('Tests Query').should('have.attr', 'href').and('contains', default_filters);
  });
});
