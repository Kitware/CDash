describe('expected_build', () => {
  beforeEach(() => {
    // cleanup before test in case previous run failed mid-way
    cy.login();
    cy.visit('index.php?project=InsightExample&date=2018-08-09');
    cy.get('[data-cy="build-admin-options"]').first().click();
    cy.get('[data-cy="build-admin-options-panel"]').then(($panel) => {
      const btn = $panel.find('[data-cy="mark-as-non-expected-btn"]');
      if (btn.length > 0) {
        cy.wrap(btn).click();
      }
    });
  });

  it('toggles expected mode for build and displays it in future table', () => {
    cy.visit('index.php?project=InsightExample');
    cy.get('#project_5_15').find('tr').last().find('td').eq(1).should('not.contain', 'test-build-relationships');

    // navigate to 'prev' page
    cy.get('a').contains('Prev').click();
    cy.url().should('contain', 'index.php?project=InsightExample&date=2018-08-09');

    // locate the admin options icon and open it
    cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').should('contain', 'test-build-relationships');
    cy.get('@build_td').find('[data-cy="build-admin-options"]').click();

    // status should start as not expected
    cy.get('[data-cy="build-admin-options-panel"]').should('be.visible');
    cy.get('[data-cy="expected-status-label"]').should('contain', 'This build is not expected to submit');
    cy.get('[data-cy="mark-as-expected-btn"]').click();

    // refresh the page to make sure this build is now expected
    cy.reload();
    cy.get('[data-cy="build-admin-options"]').first().click();
    cy.get('[data-cy="expected-status-label"]').should('contain', 'This build is expected to submit');
    cy.get('[data-cy="mark-as-expected-btn"]').should('not.exist');
    cy.get('[data-cy="mark-as-non-expected-btn"]').should('exist');
    cy.get('[data-cy="mark-as-non-expected-btn"]').should('contain', 'Mark as Not Expected');

    // 'latest' should now display 'test-build-relationships' with unknown start time
    cy.get('a').contains('Latest').click();
    cy.get('#project_5_15').find('tr').last().should('contain', 'test-build-relationships');
    cy.get('#project_5_15').find('tr').last().find('td').eq(-2).should('contain', 'Expected build');

    // restore it to not be expected
    cy.get('a').contains('Prev').click();
    cy.get('[data-cy="build-admin-options"]').first().click();
    cy.get('[data-cy="mark-as-non-expected-btn"]').click();

    // refresh & verify
    cy.reload();
    cy.get('[data-cy="build-admin-options"]').first().click();
    cy.get('[data-cy="expected-status-label"]').should('contain', 'This build is not expected to submit');
    cy.get('[data-cy="mark-as-non-expected-btn"]').should('not.exist');
    cy.get('[data-cy="mark-as-expected-btn"]').should('exist');
  });

  it('displays move-to dropdown excluding current group', () => {
    cy.visit('index.php?project=InsightExample&date=2018-08-09');

    cy.get('#project_5_15').parents('.buildgroup').first().as('buildgroup');
    cy.get('@buildgroup').find('a.grouptrigger').invoke('text').then((currentGroupName) => {
      const groupName = currentGroupName.trim();

      cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
      cy.get('@build_td').find('[data-cy="build-admin-options"]').click();

      cy.get('[data-cy="build-admin-options-panel"]').should('be.visible');
      cy.get('[data-cy="expected-status-label"]').should('contain', 'This build is not expected to submit');
      cy.get('[data-cy="move-to-group-btn"]').should('be.disabled');

      cy.get('[data-cy="move-to-group-select"]').find('option').should('have.length.at.least', 2);
      cy.get('[data-cy="move-to-group-select"]').find('option').then(($options) => {
        const optionTexts = [...$options].map((o) => o.textContent.trim());
        expect(optionTexts).to.not.include(groupName);
      });

      cy.get('[data-cy="move-to-group-select"]').find('option').eq(1).then(($opt) => {
        cy.get('[data-cy="move-to-group-select"]').select($opt.val());
      });
      cy.get('[data-cy="move-to-group-btn"]').should('not.be.disabled');
    });
  });

  it('moves a build to another group via dropdown', () => {
    cy.visit('index.php?project=InsightExample&date=2018-08-09');

    const buildName = 'test-build-relationships';
    cy.get('#project_5_15').parents('.buildgroup').first().as('source_group');
    cy.get('@source_group').find('a.grouptrigger').invoke('text').then((sourceGroupName) => {
      const sourceName = sourceGroupName.trim();

      cy.get('#project_5_15').find('tbody').find('tr').first().find('td').eq(1).as('build_td');
      cy.get('@build_td').should('contain', buildName);
      cy.get('@build_td').find('[data-cy="build-admin-options"]').click();

      cy.get('[data-cy="move-to-group-select"]').find('option').eq(1).then(($opt) => {
        const targetGroupId = $opt.val();
        const targetGroupName = $opt.text().trim();

        cy.window().then((w) => {
          w.beforeMoveReload = true;
        });
        cy.get('[data-cy="move-to-group-select"]').select(targetGroupId);
        cy.get('[data-cy="move-to-group-btn"]').click();

        cy.window().should('not.have.property', 'beforeMoveReload');
        cy.url().should('contain', 'index.php?project=InsightExample&date=2018-08-09');

        // build should appear in the destination group
        cy.contains('.buildgroup', targetGroupName).should('contain', buildName);

        // move it back to the original group
        cy.contains('.buildgroup', targetGroupName).within(() => {
          cy.contains('tr', buildName).find('[data-cy="build-admin-options"]').click();
          cy.get('[data-cy="move-to-group-select"]').select(sourceName);
          cy.window().then((w) => {
            w.beforeMoveBackReload = true;
          });
          cy.get('[data-cy="move-to-group-btn"]').click();
        });

        cy.window().should('not.have.property', 'beforeMoveBackReload');
        cy.contains('.buildgroup', sourceName).should('contain', buildName);
      });
    });
  });

  it('batch marks multiple builds as expected and not expected', () => {
    // navigate to the page with builds
    cy.visit('index.php?project=InsightExample&date=2010-07-07');

    // enable bulk selection mode
    cy.get('[data-cy="bulk-select-toggle-btn"]').click();
    cy.get('[data-cy="bulk-select-toggle-btn"]').should('contain', 'Exit Selection');

    // verify checkboxes are now visible
    cy.get('[data-cy="build-selection-checkbox"]').should('exist');

    // select the first two builds
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-selection-checkbox"]').check();
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-selection-checkbox"]').check();
    cy.contains('2 build(s) selected').should('be.visible');

    // click the "Mark as Expected" button in the bulk actions toolbar
    cy.window().then((w) => w.beforeReload1 = true);
    cy.get('[data-cy="bulk-mark-expected-btn"]').click();

    // wait for page reload
    cy.window().should('not.have.property', 'beforeReload1');
    cy.url().should('contain', 'index.php?project=InsightExample&date=2010-07-07');

    // verify first build is now expected
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-admin-options"]').click();
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-admin-options-panel"]').should('be.visible');
    cy.get('[data-cy="expected-status-label"]').first().should('contain', 'This build is expected to submit');
    cy.get('[data-cy="mark-as-non-expected-btn"]').first().should('exist');
    cy.get('[data-cy="mark-as-expected-btn"]').should('not.exist');
    // close admin options
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-admin-options"]').click();

    // Check second build
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-admin-options"]').click();
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-admin-options-panel"]').should('be.visible');
    cy.get('[data-cy="expected-status-label"]').should('contain', 'This build is expected to submit');
    cy.get('[data-cy="mark-as-non-expected-btn"]').should('exist');
    cy.get('[data-cy="mark-as-expected-btn"]').should('not.exist');
    // close admin options
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-admin-options"]').click();

    // re-enable bulk selection mode to mark them back as not expected
    cy.get('[data-cy="bulk-select-toggle-btn"]').click();

    // select the same builds again
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-selection-checkbox"]').check();
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-selection-checkbox"]').check();

    // mark them as not expected
    cy.window().then((w) => w.beforeReload2 = true);
    cy.get('[data-cy="bulk-mark-not-expected-btn"]').click();

    // wait for page reload
    cy.window().should('not.have.property', 'beforeReload2');
    cy.url().should('contain', 'index.php?project=InsightExample&date=2010-07-07');

    // verify first build is now not expected
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-admin-options"]').click();
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-admin-options-panel"]').should('be.visible');
    cy.get('[data-cy="expected-status-label"]').first().should('contain', 'This build is not expected to submit');
    cy.get('[data-cy="mark-as-expected-btn"]').first().should('exist');
    cy.get('[data-cy="mark-as-non-expected-btn"]').should('not.exist');
    // close admin options
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-admin-options"]').click();

    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-admin-options"]').click();
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-admin-options-panel"]').should('be.visible');
    cy.get('[data-cy="expected-status-label"]').should('contain', 'This build is not expected to submit');
    cy.get('[data-cy="mark-as-expected-btn"]').should('exist');
    cy.get('[data-cy="mark-as-non-expected-btn"]').should('not.exist');
    // close admin options
    cy.get('#project_5_13').find('tbody').find('tr').eq(1).find('[data-cy="build-admin-options"]').click();

    // re-enable bulk selection mode to test clear selection
    cy.get('[data-cy="bulk-select-toggle-btn"]').click();

    // test "Clear Selection" button
    cy.get('#project_5_13').find('tbody').find('tr').eq(0).find('[data-cy="build-selection-checkbox"]').check();
    cy.contains('1 build(s) selected').should('be.visible');
    cy.get('[data-cy="clear-selection-btn"]').click();
    cy.contains('build(s) selected').should('not.exist');

    // exit selection mode
    cy.get('[data-cy="bulk-select-toggle-btn"]').click();
    cy.get('[data-cy="bulk-select-toggle-btn"]').should('contain', 'Bulk Select');
    cy.get('[data-cy="build-selection-checkbox"]').should('not.exist');
  });
});
