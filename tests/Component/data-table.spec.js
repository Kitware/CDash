import { describe, expect, it } from 'vitest';
import { render } from 'vitest-browser-vue';
import DataTable from '../../resources/js/vue/components/shared/DataTable.vue';

describe('Data table component tests', () => {
  const rows = (screen) => screen.getByTestId('data-table-row');
  const cell = (screen, row, column) => rows(screen).nth(row).getByTestId('data-table-cell').nth(column);

  it('Handles unsorted text data', async () => {
    const screen = await render(DataTable, {
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

    // No sort icons in the column headers.
    expect(screen.container.querySelector('th svg')).toBeNull();

    await expect.element(rows(screen)).toHaveLength(3);

    await expect.element(cell(screen, 0, 0)).toMatchTextContent('Data value 1');
    await expect.element(cell(screen, 0, 1)).toMatchTextContent('Data value 2');

    await expect.element(cell(screen, 1, 0)).toMatchTextContent('Data value 5');
    await expect.element(cell(screen, 1, 1)).toMatchTextContent('Data value 6');

    await expect.element(cell(screen, 2, 0)).toMatchTextContent('Data value 3');
    await expect.element(cell(screen, 2, 1)).toMatchTextContent('Data value 4');
  });

  it('Sorts text data', async () => {
    const screen = await render(DataTable, {
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

    // The column headers don't have the columnheader role without a scope attribute.
    const header = screen.getByText('Test');
    await expect.element(header).toBeVisible();
    await expect.element(screen.getByText('Test 2')).toBeVisible();

    await expect.element(rows(screen)).toHaveLength(3);

    // Initially sorted by the second column
    await expect.element(cell(screen, 0, 0)).toMatchTextContent('Data value 2');
    await expect.element(cell(screen, 0, 1)).toMatchTextContent('Data value 4');

    // Can't test the first column values, because they aren't deterministic
    await expect.element(cell(screen, 1, 1)).toMatchTextContent('Data value 5');
    await expect.element(cell(screen, 2, 1)).toMatchTextContent('Data value 5');

    // Sort the first column
    await header.click();

    await expect.element(cell(screen, 0, 0)).toMatchTextContent('Data value 1');
    await expect.element(cell(screen, 0, 1)).toMatchTextContent('Data value 5');

    await expect.element(cell(screen, 1, 0)).toMatchTextContent('Data value 2');
    await expect.element(cell(screen, 1, 1)).toMatchTextContent('Data value 4');

    await expect.element(cell(screen, 2, 0)).toMatchTextContent('Data value 3');
    await expect.element(cell(screen, 2, 1)).toMatchTextContent('Data value 5');

    // Switch the sort direction of the first column and check again
    await header.click();

    await expect.element(cell(screen, 0, 0)).toMatchTextContent('Data value 3');
    await expect.element(cell(screen, 0, 1)).toMatchTextContent('Data value 5');

    await expect.element(cell(screen, 1, 0)).toMatchTextContent('Data value 2');
    await expect.element(cell(screen, 1, 1)).toMatchTextContent('Data value 4');

    await expect.element(cell(screen, 2, 0)).toMatchTextContent('Data value 1');
    await expect.element(cell(screen, 2, 1)).toMatchTextContent('Data value 5');
  });

  it('Handles text with value without link', async () => {
    const screen = await render(DataTable, {
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
              text: 'text1',
              value: 'value1',
            },
          },
        ],
        sortable: false,
      },
    });

    await expect.element(cell(screen, 0, 0)).toHaveTextContent('text1');
  });

  it('Handles links', async () => {
    const screen = await render(DataTable, {
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
              text: 'Link 1',
              href: 'http://localhost:8080/test',
            },
          },
        ],
      },
    });

    await expect.element(cell(screen, 0, 0)).toHaveTextContent('Data value 1');
    await expect.element(cell(screen, 0, 1)).toHaveTextContent('Link 1');
    await expect.element(cell(screen, 0, 1).getByRole('link')).toHaveAttribute('href', 'http://localhost:8080/test');
  });

  it('Handles custom components', async () => {
    const screen = await render(DataTable, {
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

    await expect.element(cell(screen, 0, 0)).toContainHTML('<p>Test 1 Prop static text</p>');
  });

  it('Sorts custom components', async () => {
    const screen = await render(DataTable, {
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

    await expect.element(rows(screen)).toHaveLength(2);

    // Make sure we can sort by the value attribute
    await expect.element(cell(screen, 0, 0)).toContainHTML('<p>Test 1 Prop static text</p>');
    await expect.element(cell(screen, 1, 0)).toContainHTML('<p>Test 2 Prop static text</p>');

    // Change the sort direction
    await screen.getByText('Test').click();

    await expect.element(cell(screen, 0, 0)).toContainHTML('<p>Test 2 Prop static text</p>');
    await expect.element(cell(screen, 1, 0)).toContainHTML('<p>Test 1 Prop static text</p>');
  });

  it('Does not show empty table message when not empty', async () => {
    const screen = await render(DataTable, {
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
        emptyTableText: 'empty table text',
      },
    });

    await expect.element(screen.getByTestId('data-table')).not.toMatchTextContent('empty table text');
  });

  it('Shows empty table message when empty', async () => {
    const screen = await render(DataTable, {
      props: {
        columns: [
          {
            displayName: 'Test',
            name: 'test',
          },
        ],
        rows: [],
        emptyTableText: 'empty table text',
      },
    });

    await expect.element(screen.getByTestId('data-table')).toMatchTextContent('empty table text');
  });
});
