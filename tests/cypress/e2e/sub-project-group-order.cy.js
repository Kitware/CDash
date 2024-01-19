describe('subProjectGroupOrder', () => {

  it('can change the group order', () => {
    cy.login();
    cy.visit('index.php?project=CrossSubProjectExample');

    // hover over 'Settings' and click 'SubProjects'
    cy.get('#admin').contains('a', 'SubProjects').click({ force: true });
    cy.url().should('contain', 'manageSubProject.php?projectid=16');

    // navigate to the 'SubProjects Groups' tab
    cy.get('a').contains('SubProject Groups').click();

    // drag and drop the Production group to the top of the list
    // TODO: (sbelsk) cypress can't see the values bounded by Angular in text
    //   inputs. Uncomment this line once this page is reimplemented in Vue.
    // cy.get('tbody#sortable').contains('tr', 'Production').as('prod_group_tr');
    cy.get('tbody#sortable').find('tr').last().find('td').eq(1).as('prod_group_tr');
    cy.get('tbody#sortable').find('tr').eq(0).as('drag_through');
    cy.get('table[data-cy="existing-subproject-groups"]').find('thead').as('drop_position');

    cy.get('@prod_group_tr').trigger('mousedown', { which: 1 });
    cy.get('@drag_through').trigger('mousemove').wait(200); // must drag it over other rows for it to work
    cy.get('@drop_position').trigger('mousemove').wait(200).trigger('mouseup', {force: true});

    cy.contains('button', 'Save Order').as('save_order_button').click();

    // TODO: (sbelsk) uncomment these as well, once in Vue
    // cy.get('#sortable').find('tr').first().find('input[name="group_name"]').should('contain', 'Production');
    // make sure it's still on the top after we refresh
    // cy.reload();
    // cy.get('a').contains('SubProject Groups').click();
    // cy.get('#sortable').find('tr').first().find('input[name="group_name"]').should('contain', 'Production');

    // navigate to our example of coverage across groups
    cy.visit('index.php?project=CrossSubProjectExample&parentid=119');

    // make sure that Production is the first group listed after Total
    cy.get('#coveragetable').find('tbody').eq(1).should('contain', 'Production'); // this page has some cursed html

    // restore group order
    cy.visit('manageSubProject.php?projectid=16');
    cy.get('a').contains('SubProject Groups').click();
    // cy.get('tbody#sortable').contains('tr', 'Production').as('prod_group_tr'); // TODO: (sbelsk) this too
    cy.get('tbody#sortable').find('tr').first().find('td').eq(1).as('prod_group_tr');
    cy.get('tbody#sortable').find('tr').last().as('drag_through');
    cy.get('@save_order_button').as('drop_position');

    cy.get('@prod_group_tr').trigger('mousedown', { which: 1 });
    cy.get('@drag_through').trigger('mousemove').wait(200); // must drag it over other rows for it to work
    cy.get('@drop_position').trigger('mousemove').wait(200).trigger('mouseup', {force: true});
    cy.get('@save_order_button').click();

    // verify that we restored it
    cy.visit('index.php?project=CrossSubProjectExample&parentid=119');
    cy.get('#coveragetable').find('tbody').eq(3).should('contain', 'Production');
  });
});
