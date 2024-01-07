describe('filterLabels', () => {
  it('passes filters to the viewTest page', () => {
    cy.visit('index.php?project=Trilinos&date=2011-07-22');
    cy.get('a').contains('Windows_NT-MSVC10-SERIAL_DEBUG_DEV').click();
    cy.url().should('contain', 'index.php?project=Trilinos&parentid=12');

    // first, verify the expected number of builds
    cy.get('#numbuilds').should('contain', 'Number of SubProjects: 36');
    // note: A maximum of 10 builds are displayed at a time
    cy.get('tbody').find('tr').should('have.length', 10);

    // apply filter parameters
    cy.get('#settings').click();
    cy.get('#label_showfilters').click();
    cy.get('#id_field1').select('Label');
    cy.get('#id_compare1').select('contains');
    cy.get('#id_value1').type('ra');
    cy.get('input[name="apply"]').click();

    // make sure the expected number of builds are displayed
    cy.get('#numbuilds').should('contain', 'Number of SubProjects: 5');
    cy.get('tbody').find('tr').should('have.length', 5);

    // next, click on a specific test
    cy.get('tbody').find('tr').eq(3).find('td').eq(7).as('failing_test');
    cy.get('@failing_test').should('contain', '10');
    cy.get('@failing_test').find('a').click();
    cy.url().should('contain', 'viewTest.php?onlyfailed&buildid=13&filtercount=1&showfilters=1&field1=label&compare1=63&value1=ra');

    // make sure the expected number of tests are displayed
    cy.get('#viewTestTable').find('tbody').find('tr').should('have.length', 10);
  });


  it('inserts new filters in the right order', () => {
    cy.visit('index.php?project=Trilinos&date=2011-07-22&showfilters=1');

    // add two filters to the form.
    cy.get('#id_field1').select('Label');
    cy.get('#id_compare1').select('contains');
    cy.get('#id_value1').type('a');

    cy.get('button[name="add1"]').click();
    cy.get('#id_value2').clear().type('b');

    // add a third one in between the two.
    cy.get('button[name="add1"]').click();

    // verify that the second filter in the list is now 'a', not 'b'.
    cy.get('#id_value2').should('have.value', 'a');
    cy.get('#id_value3').should('have.value', 'b');

    cy.get('input[name="apply"]').click();

    cy.url().should('contain', 'index.php?project=Trilinos&date=2011-07-22&filtercount=3&showfilters=1&filtercombine=and&field1=label&compare1=63&value1=a&field2=label&compare2=63&value2=a&field3=label&compare3=63&value3=b');
  });
});
