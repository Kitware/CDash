describe('build notes', () => {
  it('can be reached from the index page', () => {
    cy.visit('index.php?project=EmailProjectExample');
    cy.get('tbody').find('tr').first().find('td').eq(1).find('a[name="notesLink"]').as('notes_link');
    cy.get('@notes_link').find('img').should('have.attr', 'src', 'img/document.png');
    cy.get('@notes_link').click();
    cy.url().should('match', /builds\/[0-9]+\/notes/);
  });

  it('displays contents of note', () => {
    cy.visit('index.php?project=EmailProjectExample');
    cy.get('tbody').find('a[name="notesLink"]').click();

    // verify content of note
    cy.get('#note0').should('contain', '/cdash/_build/app/cdash/tests/ctest/ctestdriver-svnUpdates.ctest');
    // some expected code from the attached note
    cy.get('#notetext0')
      .should('contain', 'cmake_minimum_required')
      .and('contain', 'submit.php?project=EmailProjectExample');

    // toggle the note
    cy.get('#note0').find('i').as('toggle_button');
    cy.get('@toggle_button').should('have.class', 'glyphicon-chevron-down');
    cy.get('@toggle_button').click();

    // TODO: (sbelsk) perhaps the choice of icons for the
    //   collapsed and open modes should be revisited...
    cy.get('@toggle_button').should('have.class', 'glyphicon-chevron-right');
    cy.get('#notetext0').should('not.be.visible');
    cy.get('@toggle_button').click();

    // assert page is back to original state
    cy.get('@toggle_button').should('have.class', 'glyphicon-chevron-down');
    cy.get('#notetext0').should('be.visible');
  });
});
