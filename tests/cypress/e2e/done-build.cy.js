describe('done_build', () => {

  function toggle_done(index_url, is_done_by_default) {
    cy.login();
    cy.visit(index_url);

    // locate the folder icon and click it
    cy.get('table').first().find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();

    // find the 'mark as [not] done' link and click it
    const done_text = 'mark as not done';
    const not_done_text = 'mark as done';
    const old_text = is_done_by_default ? done_text : not_done_text;
    const new_text = is_done_by_default ? not_done_text : done_text;
    cy.get('@build_td').contains('a', old_text).click();

    // refresh the page to make sure this build's "doneness" was changed
    cy.visit(index_url);
    cy.get('table').first().find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();
    cy.get('@build_td').find('a').contains(old_text).should('not.exist');
    cy.get('@build_td').find('a').contains(new_text).should('exist');

    // toggle it back to its original state
    cy.get('@build_td').find('a').contains(new_text).click();

    // refresh & verify
    cy.visit(index_url);
    cy.get('table').first().find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();
    cy.get('@build_td').find('a').contains(old_text).should('exist');
    cy.get('@build_td').find('a').contains(new_text).should('not.exist');
  }

  it('toggles "done" status for normal build', () => {
    toggle_done('index.php?project=InsightExample', true);
  });

  it('toggles "done" status for parent build', () => {
    toggle_done('index.php?project=Trilinos&date=2011-07-22', false);
  });
});
