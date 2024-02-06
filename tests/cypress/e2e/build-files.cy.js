describe('The Build Configure Page', () => {
  it('can be reached from the index page', () => {
    cy.visit('index.php?project=TestCompressionExample');

    cy.get('table#project_1_3').find('tbody').find('tr').first().as('build_row');
    cy.get('@build_row').find('td').eq(1).find('a[title="2 files uploaded with this build"]').as('files_link');
    cy.get('@files_link').should('have.attr', 'href').and('match', /builds?\/[0-9]+\/files/);
    cy.get('@files_link').find('img').should('have.attr', 'src', 'img/package.png');
    cy.get('@files_link').click();
    cy.url().should('match', /builds?\/[0-9]+\/files/);
  });


  it('loads the right file data', () => {
    cy.visit('index.php?project=TestCompressionExample');

    cy.get('table#project_1_3').contains('a', 'RemovalWorksAsExpected').invoke('attr', 'href').then(build_url => {
      const buildid = build_url.match(/builds?\/([0-9]+)/)[1];
      cy.visit(`build/${buildid}/files`);

      cy.contains('a', 'RemovalWorksAsExpected').invoke('attr', 'href')
        .should('match', new RegExp (`builds?\\/${buildid}`));

      // verify contents of the page
      cy.get('h3').should('contain', 'URLs or Files submitted with this build');
      cy.get('#filesTable').find('tbody').find('tr').as('table_rows').should('have.length', 2);

      cy.get('#filesTable').find('thead').find('th').eq(0).should('contain', 'File');
      cy.get('#filesTable').find('thead').find('th').eq(1).should('contain', 'Size');
      cy.get('#filesTable').find('thead').find('th').eq(2).should('contain', 'SHA-1');

      cy.get('@table_rows').eq(0).find('td').eq(1).should('contain', '418 b');
      cy.get('@table_rows').eq(0).find('td').eq(2).should('contain', '3dca430f053532f670584760f41ce9ac0779dd9f');
      cy.get('@table_rows').eq(1).find('td').eq(1).should('contain', '1016 b');
      cy.get('@table_rows').eq(1).find('td').eq(2).should('contain', '4bbfc85cc17f93a41457544319eb664a0955a845');

      // verify the file names and urls
      cy.get('@table_rows').eq(0).find('td').eq(0).find('a').as('file1_td');
      cy.get('@table_rows').eq(1).find('td').eq(0).find('a').as('file2_td');

      const file_url_regex = new RegExp(`builds?\\/${buildid}\\/files?\\/[0-9]+`);

      cy.get('@file1_td').find('img').should('have.attr', 'src').and('contain', 'img/package.png');
      cy.get('@file1_td').should('contain', 'smile.gif');
      cy.get('@file1_td').invoke('attr', 'href').should('match', file_url_regex);

      cy.get('@file2_td').find('img').should('have.attr', 'src').and('contain', 'img/package.png');
      cy.get('@file2_td').should('contain', 'smile2.gif');
      cy.get('@file2_td').invoke('attr', 'href').should('match', file_url_regex);
      // TODO: test that the path acutally works and the file is downloadable
    });
  });
});
