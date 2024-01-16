describe('sort_index', () => {

  // these two "verify_<>_cell" functions help getting the text from all
  // child elements of the td, since it may contain links, formatting, etc.
  function _verify_cell(position, td_alias, expected_value) {
    cy.get(td_alias).invoke(position).invoke('text').then(td_text => {
      cy.wrap(td_text.trim()).should('contain', expected_value);
    });
  }

  function verify_first_cell(td_alias, expected_value) {
    _verify_cell('first', td_alias, expected_value);
  }

  function verify_last_cell(td_alias, expected_value) {
    _verify_cell('last', td_alias, expected_value);
  }

  function sort_test(field, column_index, first_value, last_value) {
    // mitigate default sorting by clicking on some other header first
    const different_index = column_index === 0 ? 1 : 0;
    cy.get('tr.table-heading > th').eq(different_index).click();

    // click the header to sort by the specified field
    cy.get('tr.table-heading > th').eq(column_index).should('contain', field).as('header');
    cy.get('@header').click();

    // make sure the right sort indicator is displayed
    cy.get('@header').find('span').should('have.class', 'glyphicon-chevron-down');

    // filter out the table cells from the targeted column
    cy.get('tbody').find(`tr td:visible:nth-child(${column_index+1})`).as('column_tds');

    // check that the expected value is at the top of the list
    verify_first_cell('@column_tds', first_value);
    verify_last_cell('@column_tds', last_value);

    // reverse order & check values again
    cy.get('@header').click();
    cy.get('@header').find('span').should('have.class', 'glyphicon-chevron-up');
    cy.get('tbody').find(`tr td:visible:nth-child(${column_index+1})`).as('column_tds');
    verify_first_cell('@column_tds', last_value);
    verify_last_cell('@column_tds', first_value);

    // reload the page and make sure we get the same result
    // to test that our cookies are set & read correctly
    cy.reload();
    verify_first_cell('@column_tds', last_value);
    verify_last_cell('@column_tds', first_value);
  }

  beforeEach(() => {
    cy.clearCookies();
    cy.visit('index.php?project=InsightExample&date=2010-07-07');
  });

  it('is sortable by Site', () => {
    sort_test('Site', 0, 'thurmite.kitware', 'dash13.kitware');
  });

  it('is sortable by Build Name', () => {
    sort_test('Build Name', 1, 'zApps-Win32-vs60', 'zApp-Win64-Vista-vs9-Release');
  });

  it('is sortable by Updated Files', () => {
    sort_test('Files', 2, '4', '0');
  });

  it('is sortable by Configure Errors', () => {
    sort_test('Error', 3, '2', '0');
  });

  it('is sortable by Configure Warnings', () => {
    sort_test('Warn', 4, '2', '0');
  });

  it('is sortable by Build Errors', () => {
    sort_test('Error', 5, '2', '0');
  });

  it('is sortable by Build Warnings', () => {
    sort_test('Warn', 6, '3', '0');
  });

  it('is sortable by Test Not Run', () => {
    sort_test('Not Run', 7, '3', '1');
  });

  it('is sortable by Test Fail', () => {
    sort_test('Fail', 8, '3', '1');
  });

  it('is sortable by Test Pass', () => {
    sort_test('Pass', 9, '3', '1');
  });

  it('is sortable by Start Time', () => {
    sort_test('Start Time', 10, 'Jul 07, 2010 - 12:26 UTC', 'Jul 07, 2010 - 12:22 UTC');
  });

  it('can be sorted by multiple columns', () => {
    const site_col = 0;
    const time_col = 10;
    const files_col = 2;

    const time_first_value = 'Jul 07, 2010 - 12:26 UTC';
    const time_last_value = 'Jul 07, 2010 - 12:22 UTC';
    const files_first_value = '4';
    const files_last_value = '0';

    // clear default sorting by clicking on the Site header
    cy.get('tr.table-heading > th').eq(site_col).click();

    // click the Build Time header
    cy.get('tr.table-heading > th').eq(time_col).as('buildtime_header').click();
    cy.get('@buildtime_header').find('span').should('have.class', 'glyphicon-chevron-down');

    // then hold down shift and click on Files header
    cy.get('tr.table-heading > th').eq(files_col).as('files_header').click({ shiftKey: true });

    // make sure sort indicators on both columns are displayed
    cy.get('@buildtime_header').find('span').should('have.class', 'glyphicon-chevron-down');
    cy.get('@files_header').find('span').should('have.class', 'glyphicon-chevron-down');

    // get cells from each of the two columns
    cy.get('tbody').find(`tr td:visible:nth-child(${time_col+1})`).as('time_column_tds');
    cy.get('tbody').find(`tr td:visible:nth-child(${files_col+1})`).as('files_column_tds');

    // check for the expected Build Time values
    verify_first_cell('@time_column_tds', time_first_value);
    verify_last_cell('@time_column_tds', time_last_value);
    // do the same for the expected Files values
    verify_first_cell('@files_column_tds', files_first_value);
    verify_last_cell('@files_column_tds', files_last_value);

    // reverse order & check values again
    cy.get('@buildtime_header').click();
    cy.get('@files_header').click({ shiftKey: true }).click({ shiftKey: true });
    // check both icons
    cy.get('@buildtime_header').find('span').should('have.class', 'glyphicon-chevron-up');
    cy.get('@files_header').find('span').should('have.class', 'glyphicon-chevron-up');
    // verify values
    cy.get('tbody').find(`tr td:visible:nth-child(${time_col+1})`).as('time_column_tds');
    cy.get('tbody').find(`tr td:visible:nth-child(${files_col+1})`).as('files_column_tds');
    verify_first_cell('@time_column_tds', time_last_value);
    verify_last_cell('@time_column_tds', time_first_value);
    verify_first_cell('@files_column_tds', files_last_value);
    verify_last_cell('@files_column_tds', files_first_value);

    // reload the page and make sure sort order is preserved
    cy.reload();
    verify_first_cell('@time_column_tds', time_last_value);
    verify_last_cell('@time_column_tds', time_first_value);
    verify_first_cell('@files_column_tds', files_last_value);
    verify_last_cell('@files_column_tds', files_first_value);
  });

});
