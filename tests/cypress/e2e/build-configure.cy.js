describe('The Build Configure Page', () => {
  it('can be reached from the index page', () => {
    cy.visit('index.php?project=InsightExample');

    cy.get('table#project_5_15').find('tbody').find('tr').first().as('build_row');
    cy.get('@build_row').find('td').each((table_entry, col) => {
      if (col === 2 || col === 3) {
        // check urls in both columns under the 'Configure' header
        cy.wrap(table_entry).find('a').as(`configure_url_${col}`).should('have.attr', 'href').and('match', /builds?\/[0-9]+\/configure/);
      }
    });
    cy.get('@configure_url_2').click();
    cy.get('#subheadername').should('contain', 'InsightExample').and('contain', 'Configure');
  });


  it('can be reached from the build summary page', () => {
    cy.visit('index.php?project=InsightExample');

    // click on one of the builds in the index page table
    cy.get('table#project_5_15').find('tbody').contains('a', 'CDash-CTest-simple').click();
    cy.url().then(build_url => {
      const buildid = build_url.match(/builds?\/([0-9]+)/)[1];
      expect(buildid).to.not.be.null;

      cy.get('a#configure_link').as('configure_link')
        .should('have.attr', 'href').and('match', /builds?\/[0-9]+\/configure/);
      cy.get('@configure_link').click();
      cy.get('#subheadername').should('contain', 'InsightExample').and('contain', 'Configure');
    });
  });


  it('loads the right configure data', () => {
    cy.visit('index.php?project=InsightExample');
    cy.get('table#project_5_15').contains('a', 'CDash-CTest-simple2').invoke('attr', 'href').then(build_url => {
      const buildid = build_url.match(/builds?\/([0-9]+)/)[1];
      cy.visit(`builds/${buildid}/configure`);

      cy.contains('a', 'CDash-CTest-simple2').invoke('attr', 'href')
        .should('match', new RegExp (`builds?\\/${buildid}`));

      const configure_cmd = '"/usr/bin/cmake" "-GUnix Makefiles" "/cdash/app/cdash/tests/ctest/simple2"';
      cy.contains('b', 'Configure Command:').siblings('pre').should('contain', configure_cmd);

      const configure_rc = '0';
      cy.contains('b', 'Configure Return Value:').siblings('pre').should('contain', configure_rc);

      const configure_output_line = '-- Build files have been written to: /cdash/_build/tests/ctest/simple2';
      cy.contains('b', 'Configure Output:').siblings('pre').should('contain', configure_output_line);
    });
  });
});
