/**
 * TODO: Fill out this test once seed data for the build measurements functionality becomes available.
 *       For now, we just verify that the page loads with no errors.
 */
describe('Build measurements page', () => {
  it('Loads page successfully', () => {
    cy.visit('/builds/372/measurements');
    cy.get('#headername2').should('not.contain', '404 Not Found');
  });

  it('Shows 404 if build does not exist', () => {
    cy.request({url: '/builds/123456789/measurements', failOnStatusCode: false}).should('have.property', 'status', 404);
    cy.visit({url: '/builds/123456789/measurements', failOnStatusCode: false});
    cy.get('#headername2').should('contain', '404 Not Found');
  });
});
