describe('viewBuildError', () => {

  function get_whitespace_regex(input_str) {
    const ws = '\\s+'; // matches any whitespace
    return new RegExp(ws + input_str.split(/\s+/).join(ws) + ws);
  }

  it('shows 0 errors', () => {
    // from the index page, click on the number of build errors
    cy.visit('index.php?project=BatchmakeExample');
    cy.get('tbody').contains('tr', 'Win32-MSVC2009').as('build_row');
    cy.get('@build_row').find('td').eq(5).as('build_errors_td');
    cy.get('@build_errors_td').should('contain', '0');
    cy.get('@build_errors_td').click();

    cy.url().then(build_errors_url => {
      // verify we have the right url and right data is displayed
      const expected_url = new RegExp('viewBuildError\\.php\\?buildid=\\d+$');
      cy.wrap(build_errors_url).should('match', expected_url);
      // TODO: (sbelsk) we have to do this hack to comapre the text
      //   because it's bound in angular and cypress can't get it easily.
      //   once this gets redone in Vue, we should simply do:
      //   cy.get('td.num-errors').should('contain', '0 Errors');
      cy.get('td.num-errors').invoke('text').should('match', get_whitespace_regex('0 Errors'));

      // verify that deltan shows 0 errors
      cy.visit(`${build_errors_url}&onlydeltan=1`);
      cy.get('td.num-errors').invoke('text').should('match', get_whitespace_regex('0 Errors'));

      // verify that deltap shows 0 errors
      cy.visit(`${build_errors_url}&onlydeltap=1`);
      cy.get('td.num-errors').invoke('text').should('match', get_whitespace_regex('0 Errors'));
    });
  });


  it('type=1 shows 10 warnings', () => {
    // from the index page, click on the number of build warnings
    cy.visit('index.php?project=BatchmakeExample');
    cy.get('tbody').contains('tr', 'Win32-MSVC2009').as('build_row');
    cy.get('@build_row').find('td').eq(6).as('build_warnings_td');
    cy.get('@build_warnings_td').should('contain', '10');
    cy.get('@build_warnings_td').click();

    // verify we have the right url and right data is displayed
    const expected_url = new RegExp('viewBuildError\\.php\\?type=1&buildid=\\d+$');
    cy.url().should('match', expected_url);
    cy.get('td.num-errors').invoke('text').should('match', get_whitespace_regex('10 Warnings'));
  });


  // TODO: (sbelsk) the following two tests should not specify
  //  any buildid's in case we add more data to the dev env in
  //  the future. However, there is currently no way to navigate
  //  to these pages without the hardedcoded urls.
  it('displays build errors inline', () => {
    cy.visit('viewBuildError.php?buildid=71&type=0');
    cy.get('table[data-cy="build-error-table"]').should('contain', 'error: \'foo\' was not declared in this scope');
  });


  it('displays build errors inline on parent builds', () => {
    cy.visit('viewBuildError.php?buildid=70&type=0');
    cy.get('table[data-cy="parent-build-error-table"]').find('tbody').find('tr').as('subproject_row');
    cy.get('@subproject_row').find('td').first().should('contain', 'some-test-subproject');
    cy.get('@subproject_row').find('td').eq(1).contains('a', 'Error building').click();
    cy.get('table[data-cy="build-error-table"]').should('contain', 'error: \'foo\' was not declared in this scope');
  });


  it('colorizes output', () => {
    // navigate to the build error page for a build in the OutputColor project
    cy.visit('index.php?project=OutputColor&date=2018-01-26');
    cy.get('tbody').contains('a', '5').click();
    const expected_url = new RegExp('viewBuildError\\.php\\?buildid=\\d+$');
    cy.url().should('match', expected_url);

    // check that build error log is rendered with colors
    cy.get('table[data-cy="build-error-table"]').contains('pre', 'Scanning dependencies of target colortest').as('error_log');
    cy.get('@error_log').find('span').should('have.length', 2).as('error_snippet');

    const green_text = 'Hello world!';
    cy.get('@error_snippet').eq(0).should('have.css', 'color', 'rgb(0, 187, 0)').and('contain', green_text);

    const red_text = 'Visit our website: <a href="https://www.kitware.com/">Kitware</a>';
    cy.get('@error_snippet').eq(1).should('have.css', 'color', 'rgb(255, 85, 85)').and('contain', red_text);
  });
});
