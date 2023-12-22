import DataTable from '../../../resources/js/components/shared/DataTable.vue';

it('Handles unsorted text data', () => {
  cy.mount(DataTable, {
    props: {
      columns: [
        {
          displayName: 'Test',
          name: 'test',
        },
        {
          displayName: 'Test 2',
          name: 'test2',
        },
      ],
      rows: [
        {
          test: 'Data value 1',
          test2: 'Data value 2',
        },
        {
          test: 'Data value 5',
          test2: 'Data value 6',
        },
        {
          test: 'Data value 3',
          test2: 'Data value 4',
        },
      ],
      sortable: false,
    },
  });

  cy.get('[data-cy="sort-icon"]').should('not.exist');

  cy.get('[data-cy="data-table"]').find('[data-cy="data-table-row"]').as('table-rows');
  cy.get('@table-rows').should('have.length', 3);

  cy.get('@table-rows').eq(0).find('[data-cy="data-table-cell"]').as('table-row-0');
  cy.get('@table-rows').eq(1).find('[data-cy="data-table-cell"]').as('table-row-1');
  cy.get('@table-rows').eq(2).find('[data-cy="data-table-cell"]').as('table-row-2');

  cy.get('@table-row-0').eq(0).should('contain', 'Data value 1');
  cy.get('@table-row-0').eq(1).should('contain', 'Data value 2');

  cy.get('@table-row-1').eq(0).should('contain', 'Data value 5');
  cy.get('@table-row-1').eq(1).should('contain', 'Data value 6');

  cy.get('@table-row-2').eq(0).should('contain', 'Data value 3');
  cy.get('@table-row-2').eq(1).should('contain', 'Data value 4');
});

it('Sorts text data', () => {
  cy.mount(DataTable, {
    props: {
      columns: [
        {
          displayName: 'Test',
          name: 'test',
        },
        {
          displayName: 'Test 2',
          name: 'test2',
        },
      ],
      rows: [
        {
          test: 'Data value 1',
          test2: 'Data value 5',
        },
        {
          test: 'Data value 3',
          test2: 'Data value 5',
        },
        {
          test: 'Data value 2',
          test2: 'Data value 4',
        },
      ],
      sortable: true,
      initialSortColumn: 'test2',
    },
  });

  cy.get('[data-cy="column-header"]').should('be.visible');

  cy.get('[data-cy="data-table"]').find('[data-cy="data-table-row"]').as('table-rows');
  cy.get('@table-rows').should('have.length', 3);

  cy.get('@table-rows').eq(0).find('[data-cy="data-table-cell"]').as('table-row-0');
  cy.get('@table-rows').eq(1).find('[data-cy="data-table-cell"]').as('table-row-1');
  cy.get('@table-rows').eq(2).find('[data-cy="data-table-cell"]').as('table-row-2');

  // Initially sorted by the second column
  cy.get('@table-row-0').eq(0).should('contain', 'Data value 2');
  cy.get('@table-row-0').eq(1).should('contain', 'Data value 4');

  // Can't test the first column values, because they aren't deterministic
  cy.get('@table-row-1').eq(1).should('contain', 'Data value 5');
  cy.get('@table-row-2').eq(1).should('contain', 'Data value 5');

  // Sort the first column
  cy.get('[data-cy="column-header"]').eq(0).click();

  cy.get('@table-row-0').eq(0).should('contain', 'Data value 1');
  cy.get('@table-row-0').eq(1).should('contain', 'Data value 5');

  cy.get('@table-row-1').eq(0).should('contain', 'Data value 2');
  cy.get('@table-row-1').eq(1).should('contain', 'Data value 4');

  cy.get('@table-row-2').eq(0).should('contain', 'Data value 3');
  cy.get('@table-row-2').eq(1).should('contain', 'Data value 5');

  // Switch the sort direction of the first column and check again
  cy.get('[data-cy="column-header"]').eq(0).click();

  cy.get('@table-row-0').eq(0).should('contain', 'Data value 3');
  cy.get('@table-row-0').eq(1).should('contain', 'Data value 5');

  cy.get('@table-row-1').eq(0).should('contain', 'Data value 2');
  cy.get('@table-row-1').eq(1).should('contain', 'Data value 4');

  cy.get('@table-row-2').eq(0).should('contain', 'Data value 1');
  cy.get('@table-row-2').eq(1).should('contain', 'Data value 5');
});

it('Handles links', () => {
  cy.mount(DataTable, {
    props: {
      columns: [
        {
          displayName: 'Test',
          name: 'test',
        },
        {
          displayName: 'Test 2',
          name: 'test2',
        },
      ],
      rows: [
        {
          test: 'Data value 1',
          test2: {
            value: 'Link 1',
            href: 'http://localhost:8080/test',
          },
        },
      ],
    },
  });

  cy.get('[data-cy="data-table"]').find('[data-cy="data-table-row"]').first()
    .find('[data-cy="data-table-cell"]').as('table-cells');

  cy.get('@table-cells').eq(0).should('have.text', 'Data value 1');
  cy.get('@table-cells').eq(1).should('have.text', 'Link 1').get('a')
    .invoke('attr', 'href').should('equal', 'http://localhost:8080/test');
});

it('Handles custom components', () => {
  cy.mount(DataTable, {
    props: {
      columns: [
        {
          displayName: 'Test',
          name: 'test',
        },
      ],
      rows: [
        {
          test: {
            value: 'test1',
            myprop: 'Test 1 Prop',
          },
        },
      ],
    },
    slots: {
      test: '<p>{{ props.myprop }} static text</p>',
    },
  });

  cy.get('[data-cy="data-table"]').find('[data-cy="data-table-row"]').first()
    .find('[data-cy="data-table-cell"]').first().as('table-cell');

  cy.get('@table-cell').should('contain.html', '<p>Test 1 Prop static text</p>');
});

it('Sorts custom components', () => {
  cy.mount(DataTable, {
    props: {
      columns: [
        {
          displayName: 'Test',
          name: 'test',
        },
      ],
      rows: [
        {
          test: {
            value: 'item1',
            myprop: 'Test 1 Prop',
          },
        },
        {
          test: {
            value: 'item0',
            myprop: 'Test 2 Prop',
          },
        },
      ],
      sortable: true,
      sortColumn: 'test',
      initialSortAsc: false,
    },
    slots: {
      test: '<p>{{ props.myprop }} static text</p>',
    },
  });

  cy.get('[data-cy="data-table"]').find('[data-cy="data-table-row"]').as('table-rows');
  cy.get('@table-rows').should('have.length', 2);

  cy.get('@table-rows').eq(0).find('[data-cy="data-table-cell"]').first().as('table-cell-0');
  cy.get('@table-rows').eq(1).find('[data-cy="data-table-cell"]').first().as('table-cell-1');

  // Make sure we can sort by the value attribute
  cy.get('@table-cell-0').should('contain.html', '<p>Test 1 Prop static text</p>');
  cy.get('@table-cell-1').should('contain.html', '<p>Test 2 Prop static text</p>');

  // Change the sort direction
  cy.get('[data-cy="column-header"]').eq(0).click();

  cy.get('@table-cell-0').should('contain.html', '<p>Test 2 Prop static text</p>');
  cy.get('@table-cell-1').should('contain.html', '<p>Test 1 Prop static text</p>');
});
