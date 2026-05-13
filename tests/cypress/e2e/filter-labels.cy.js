describe('filterLabels', () => {
  it('inserts new filters in the right order', () => {
    cy.visit('index.php?project=Trilinos&date=2011-07-22&showfilters=1');

    // add two filters to the form.
    cy.get('#id_field1').select('Label');
    cy.get('#id_compare1').select('contains');
    cy.get('#id_value1').type('a');

    cy.get('button[name="add1"]').click();
    cy.get('#id_value2').clear();
    cy.get('#id_value2').type('b');

    // add a third one in between the two.
    cy.get('button[name="add1"]').click();

    // verify that the second filter in the list is now 'a', not 'b'.
    cy.get('#id_value2').should('have.value', 'a');
    cy.get('#id_value3').should('have.value', 'b');

    cy.get('input[name="apply"]').click();

    cy.url().should('contain', 'index.php?project=Trilinos&date=2011-07-22&filtercount=3&showfilters=1&filtercombine=and&field1=label&compare1=63&value1=a&field2=label&compare2=63&value2=a&field3=label&compare3=63&value3=b');
  });
});
