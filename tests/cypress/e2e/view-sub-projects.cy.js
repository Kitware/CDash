describe('viewSubProjects', () => {

  it('navigates between SubProjects', () => {
    cy.visit('viewSubProjects.php?project=SubProjectExample');

    cy.get('table#subproject').contains('td', 'ThreadPool').find('a').click();
    cy.url().should('contain', 'index.php?subproject=ThreadPool&project=SubProjectExample');

    cy.get('#navigation').find('a').contains('SubProjects').click({ force: true });
    cy.url().should('contain', 'viewSubProjects.php?project=SubProjectExample');

    cy.get('table#subproject').contains('td', 'Teuchos').find('a').click();
    cy.url().should('contain', 'index.php?subproject=Teuchos&project=SubProjectExample');
  });

});
