describe('SubProjectDependencies', () => {
  it('loads the dependency graph', () => {
    cy.visit('viewSubProjectDependenciesGraph?project=SubProjectExample&date=2009-08-06%2012:19:56');

    cy.get('#chart_placeholder').should('have.descendants', 'svg');
  });


  it('can interact with the dependency graph', () => {
    cy.visit('viewSubProjectDependenciesGraph?project=SubProjectExample&date=2009-08-06%2012:19:56');

    // check that the graph sorting works
    cy.get('select').find('option').contains('subproject name').should('be.selected');
    cy.get('text.node').first().should('contain', 'Amesos');
    cy.get('select').select('subproject id');
    cy.get('text.node').first().should('contain', 'Teuchos');
    cy.get('select').select('subproject name'); // restore to default

    // check tooltip displays as expected
    cy.get('div#toolTip').should('have.css', 'opacity', '0'); // initially hidden
    cy.get('text.node').first().trigger('mouseover').then((d) => {
      // tooltip becomes visible on hover
      cy.get('div#toolTip').should('have.css', 'opacity', '0.9');
      cy.get('div#toolTip').find('div#header1').should('contain', 'Amesos');
      // 'Anasazi' is known to be a dependent of 'Amesos'
      cy.get('text.node').filter(':contains("Anasazi")').first().should('have.class', 'node--source');
      // 'Amesos' is known to depend on 'Teuchos'
      cy.get('text.node').filter(':contains("Teuchos")').first().should('have.class', 'node--target');
    });
    cy.get('text.node').first().trigger('mouseout').then((d) => {
      cy.get('div#toolTip').should('have.css', 'opacity', '0'); // should be hidden again
    });
  });
});
