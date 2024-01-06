describe('calendar', () => {
  it('toggles the calendar view', () => {
    cy.visit('index.php?project=InsightExample');

    // Verify calendar is displayed.
    cy.get('#cal').click();
    cy.get('.hasDatepicker').should('be.visible');

    // Click again and verify that calendar is hidden.
    cy.get('#cal').click();
    cy.get('.hasDatepicker').should('not.be.visible');
  });

  it('removes begin/end from URI', () => {
    cy.visit('index.php?project=InsightExample&begin=yesterday&end=today&filtercount=0&showfilters=1');

    // Click the Calendar link and pick the current testing day.
    cy.get('#cal').click();
    cy.get('.ui-datepicker-today').click();

    // Verify that the resulting URL contains a date param with no begin or end.
    const date = new Date().toISOString().slice(0, 10);
    cy.url().should('contain', `index.php?project=InsightExample&date=${date}&filtercount=0&showfilters=1`);
  });
});
