describe('SubProjectDependencies', () => {
  it('loads the dependency graph', () => {
    cy.visit('projects/SubProjectExample/subprojects/dependencies?2015-01-28%2014:36:08');

    cy.get('[data-cy="svg-wrapper"]').should('have.descendants', 'svg');
  });


  it('can interact with the dependency graph', () => {
    cy.visit('projects/SubProjectExample/subprojects/dependencies?2015-01-28%2014:36:08');

    // check that the graph sorting works
    cy.get('[data-cy="select-sorting-order"]').find('option').contains('subproject name').should('be.selected');
    cy.get('text.node').first().should('contain', 'Amesos');
    cy.get('[data-cy="select-sorting-order"]').select('subproject id');
    cy.get('text.node').first().should('contain', 'Teuchos');
    cy.get('[data-cy="select-sorting-order"]').select('subproject name'); // restore to default

    // check tooltip displays as expected
    cy.get('[data-cy="tooltip"]').should('have.css', 'opacity', '0'); // initially hidden
    cy.get('text.node').first().trigger('mouseover').then((d) => {
      // tooltip becomes visible on hover
      cy.get('[data-cy="tooltip"]').should('have.css', 'opacity', '0.9');
      cy.get('[data-cy="tooltip-name-header"]').should('contain', 'Amesos');
      // 'Anasazi' is known to be a dependent of 'Amesos'
      cy.get('text.node').filter(':contains("Anasazi")').first().should('have.class', 'node--source');
      // 'Amesos' is known to depend on 'Teuchos'
      cy.get('text.node').filter(':contains("Teuchos")').first().should('have.class', 'node--target');
    });
    cy.get('text.node').first().trigger('mouseout').then((d) => {
      cy.get('[data-cy="tooltip"]').should('have.css', 'opacity', '0'); // should be hidden again
    });
  });
});
