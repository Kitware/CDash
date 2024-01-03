describe('colorblind', () => {
  it('toggles between the two color modes', () => {
    cy.visit('index.php?project=InsightExample');

    // classic colors by default
    cy.get('[data-cy="settings-dropdown"]').click();
    cy.get('.normal').first().should('have.css', 'background-color', 'rgb(180, 220, 180)');
    // check that the dropdown option reflects the current state
    cy.get('[data-cy="color-mode-classic"]').should('not.be.visible');
    cy.get('[data-cy="color-mode-colorblind"]').should('be.visible');

    // toggle to colorblind colors
    cy.get('[data-cy="color-mode-colorblind"]').click();
    cy.get('.normal').first().should('have.css', 'background-color', 'rgb(136, 179, 206)');
    cy.get('[data-cy="settings-dropdown"]').click();
    cy.get('[data-cy="color-mode-classic"]').should('be.visible');
    cy.get('[data-cy="color-mode-colorblind"]').should('not.be.visible');

    // toggle back to the original state
    cy.get('[data-cy="color-mode-classic"]').click();
    cy.get('.normal').first().should('have.css', 'background-color', 'rgb(180, 220, 180)');
    cy.get('[data-cy="settings-dropdown"]').click();
    cy.get('[data-cy="color-mode-classic"]').should('not.be.visible');
    cy.get('[data-cy="color-mode-colorblind"]').should('be.visible');
  });
});
