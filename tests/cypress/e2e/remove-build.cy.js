describe('remove_build', () => {
  it('removes a build via the index page', () => {
    cy.login();
    cy.visit('index.php?project=InsightExample');

    // locate the folder icon for the build 'CDash-CTest-simple_async'
    cy.get('tbody').contains('tr', 'CDash-CTest-simple_async').find('td').eq(1).as('build_td');
    cy.get('@build_td').find('span[name="adminoptions"]').click();

    // find the 'Remove This Build' button and click it
    cy.get('@build_td').find('button').contains('Remove This Build').click();

    // confirm deletion in popup
    cy.get('button#modal-delete-build-button').click();
    cy.get('button').contains('cancel').click();

    // refresh the page to make sure this build is gone now
    cy.reload();
    
    cy.get('tbody').contains('tr', 'CDash-CTest-simple_async').should('not.exist');
  });
});
