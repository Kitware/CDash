describe('view coverage', () => {
  it('is reachable from the index page', () => {
    cy.visit('index.php?project=CoverageDirectories');
    cy.get('#coveragetable').find('tbody').find('tr').first().find('td').eq(2).find('a').as('covg_link');
    cy.get('@covg_link').should('have.attr', 'href').and('contain', 'viewCoverage.php?buildid');
    cy.get('@covg_link').click();
    cy.get('#subheadername').should('contain', 'CoverageDirectories').and('contain', 'Coverage');
  });

  it('is reachable from the build summary page', () => {
    cy.visit('index.php?project=CoverageDirectories');
    cy.get('#coveragetable').find('tbody').find('tr').first().find('td').eq(1).find('a').click();
    cy.url().then(buildsummary_link => {
      const buildid = buildsummary_link.match(/builds?\/([0-9]+)/)[1];
      cy.get('a#coverage_link').as('covg_link').should('have.attr', 'href').and('contain', `viewCoverage.php?buildid=${buildid}`);
      cy.get('@covg_link').click();
      cy.get('#subheadername').should('contain', 'CoverageDirectories').and('contain', 'Coverage');
    });
  });

  it('displays the coverage summary table', () => {
    cy.visit('index.php?project=CoverageDirectories');
    cy.get('#coveragetable').find('tbody').find('tr').first().find('td').eq(2).find('a').click();

    const get_cell = (row, col) => {
      return cy.get('table[data-cy="covg-summary-table"]').find('tbody').find('tr').eq(row+1).find('td').eq(col);
    };

    // check for expected data (we assume these numbers are right?)
    get_cell(0, 0).should('contain', 'Total Coverage');
    get_cell(0, 1).should('contain', '71.59');

    get_cell(1, 0).should('contain', 'Tested lines');
    get_cell(1, 1).should('contain', '252');

    get_cell(2, 0).should('contain', 'Untested lines');
    get_cell(2, 1).should('contain', '100');

    get_cell(3, 0).should('contain', 'Files Covered');
    get_cell(3, 1).should('contain', '101 of 101');

    get_cell(4, 0).should('contain', 'Files Satisfactorily Covered');
    get_cell(4, 1).should('contain', '101');

    get_cell(5, 0).should('contain', 'Files Unsatisfactorily Covered');
    get_cell(5, 1).should('contain', '0');
  });

  it('displays the graph of coverage over time', () => {
    cy.visit('index.php?project=CoverageDirectories');
    cy.get('#coveragetable').find('tbody').find('tr').first().find('td').eq(2).find('a').click();

    // expand the graph and ensure that it renders
    cy.contains('a', 'Show coverage over time').click();
    // TODO: (sbelsk) test this graph more thoroughly once it's in d3
    cy.get('#grapholder').find('canvas').should('exist');
  });

  it('renders the coverage files table', () => {
    cy.visit('index.php?project=CoverageDirectories');
    cy.get('#coveragetable').find('tbody').find('tr').first().find('td').eq(2).find('a').click();

    // check total number of rows and pagination
    cy.get('#coverageTable_length').contains('option', '25').should('be.selected');
    cy.get('#coverageTable').find('tbody').find('tr').should('have.length', 25);
    cy.get('#coverageTable_length').find('select').select('All');
    cy.get('#coverageTable').find('tbody').find('tr').should('have.length', 50);

    cy.get('#coverageTable').find('tbody').find('tr').eq(0).as('table_row');

    cy.get('@table_row').find('td').eq(1).should('contain', 'Satisfactory');
    cy.get('@table_row').find('td').eq(2).should('contain', '33.33%');
    cy.get('@table_row').find('td').eq(3).should('contain', '2/3');
    cy.get('@table_row').find('td').eq(4).should('contain', 'None');

    // check directory name and url in first column
    cy.get('#coverageTable').contains('td', 'func_15').then(directory_td => {
      cy.wrap(directory_td).find('a').as('dir_covg_link');
      cy.get('@dir_covg_link').should('have.attr', 'href').and('match', /viewCoverage.php?.*&dir=func_15/);
      // check that the link works, we expect for there to be only one file
      cy.get('@dir_covg_link').click();
      cy.get('#coverageTable').find('tbody').find('tr').should('have.length', 1);
      cy.get('#coverageTable').find('tbody').find('tr').find('td').eq(0).should('contain', 'func.cpp');
    });
  });

  // FIXME: (sbelsk) I believe the filtering UI is currently broken
  //   on this page. The 'Apply' and 'Clear' buttons don't work,
  //   and the 'Create Hyperlink' generates a console error
  /*
  it('can apply filters on the table', () => {
    cy.visit('index.php?project=CoverageDirectories');
    cy.get('#coveragetable').find('tbody').find('tr').first().find('td').eq(2).find('a').click();

    cy.get('#coverageTable_length').find('select').select('All');
    cy.get('#coverageTable').find('tbody').find('tr').should('have.length', 50);

    // click the 'Show Filters' label
    cy.get('a#label_showfilters').click();

    // filter by filename
    cy.get('select#id_field1').select('Filename');
    cy.get('select#id_compare1').select('contains');
    cy.get('input#id_value1').clear().type('5');
    // generate hyperlink
    cy.get('input[name="create_hyperlink"]').click();
    cy.get('#div_filtersAsUrl').find('a').as('filtered_filename_url');
    const filename_filter_regex = /viewCoverage.php\?buildid=[0-9]+&filtercount=1&showfilters=1&field1=filename\/string&compare1=63&value1=5/;
    cy.get('@filtered_filename_url').should('have.attr', 'href').and('match', filename_filter_regex);
    // click it to apply the filter
    cy.get('@filtered_filename_url').click();
    // count the number of results
    cy.get('#coverageTable_length').find('select').select('All');
    cy.get('#coverageTable').find('tbody').find('tr').should('have.length', 14);
  });
  */
});
