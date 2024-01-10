describe('done_build', () => {

  function validate_test(index_url, old_text, new_text) {
    cy.get('a').contains(old_text).click();

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

  function toggle_done(index_url) {
    cy.login();
    cy.visit(index_url);

    // locate the folder icon and click it
    cy.get('table').first().find('tbody').find('tr').first().find('td').eq(1).as('build_td');
    cy.get('@build_td').find('img[name="adminoptions"]').click();

    // find the 'mark as [not] done' link and click it
    cy.get('@build_td').find('div').last().then(link_wrapper => {
      if (link_wrapper.find('a:contains("mark as done")').length > 0) {
        validate_test(index_url, 'mark as done', 'mark as not done');
      }
      else {
        validate_test(index_url, 'mark as not done', 'mark as done');
      }
    });
  }

  it('toggles "done" status for normal build', () => {
    toggle_done('index.php?project=InsightExample');
  });

  it('toggles "done" status for parent build', () => {
    toggle_done('index.php?project=Trilinos&date=2011-07-22');
  });
});
