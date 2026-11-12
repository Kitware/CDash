import { DateTime } from 'luxon';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render } from 'vitest-browser-vue';
import FilterRow from '../../resources/js/vue/components/shared/FilterRow.vue';
import { FilterType, BasicFilterField } from '../../resources/js/vue/components/shared/Filters/FilterUtils';

describe('FilterRow', () => {
  let fields;

  beforeEach(() => {
    fields = [
      new BasicFilterField('Status', FilterType.ENUM, () => ['Passed', 'Failed', 'Warning'], 'status'),
      new BasicFilterField('Build Name', FilterType.TEXT, null, 'buildname'),
      new BasicFilterField('Build Time', FilterType.NUMBER, null, 'buildtime'),
      new BasicFilterField('Start Time', FilterType.DATETIME, null, 'starttime'),
    ];
  });

  it('renders with initial values', async () => {
    const initialField = fields[1]; // Build Name (TEXT)
    const initialOperator = 'contains';
    const initialValue = 'my-build';

    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField,
        initialOperator,
        initialValue,
      },
    });

    const selects = screen.getByRole('combobox');
    // The field select's options are bound to objects, so check the selected option's text instead of its value.
    await expect.element(selects.nth(0)).toHaveDisplayValue(initialField.name);
    await expect.element(selects.nth(1)).toHaveValue(initialOperator);
    await expect.element(screen.getByRole('textbox')).toHaveValue(initialValue);
  });

  it('updates operators when field changes', async () => {
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
      },
    });

    const selects = screen.getByRole('combobox');
    await expect.element(selects.nth(1).getByRole('option')).toHaveLength(3); // eq, ne, contains

    await selects.nth(0).selectOptions('Build Time'); // Change to NUMBER
    await expect.element(selects.nth(1).getByRole('option')).toHaveLength(4); // eq, ne, gt, lt
  });

  it('switches value input type based on field type', async () => {
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
      },
    });

    await expect.element(screen.getByRole('textbox')).toBeInTheDocument();

    const selects = screen.getByRole('combobox');

    await selects.nth(0).selectOptions('Build Time');
    await expect.element(screen.getByRole('spinbutton')).toBeInTheDocument();

    await selects.nth(0).selectOptions('Status');
    await expect.element(selects.nth(2)).toBeInTheDocument(); // Enum dropdown
    await expect.element(selects.nth(2).getByRole('option')).toHaveLength(3);

    await selects.nth(0).selectOptions('Start Time');
    // Check if DateTimeSelector exists (it contains many selects)
    await expect.poll(() => selects.elements().length).toBeGreaterThanOrEqual(3);
  });

  it('emits changeFilters event when value changes', async () => {
    const onChange = vi.fn();
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        initialOperator: 'eq',
        onChangeFilters: onChange,
      },
    });

    await screen.getByRole('textbox').fill('new value');
    await expect.poll(() => onChange).toHaveBeenCalledWith({
      eq: { buildname: 'new value' },
    });
  });

  it('emits changeFilters event when operator changes', async () => {
    const onChange = vi.fn();
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        initialValue: 'test',
        onChangeFilters: onChange,
      },
    });

    await screen.getByRole('combobox').nth(1).selectOptions('ne');
    await expect.poll(() => onChange).toHaveBeenCalledWith({
      ne: { buildname: 'test' },
    });
  });

  it('emits changeFilters event when field changes', async () => {
    const onChange = vi.fn();
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        initialValue: 'test',
        onChangeFilters: onChange,
      },
    });

    await screen.getByRole('combobox').nth(0).selectOptions('Build Time');
    // When field changes, value is reset to '' (for non-enum) and operator to first one (eq)
    await expect.poll(() => onChange).toHaveBeenCalledWith({
      eq: { buildtime: '' },
    });
  });

  it('handles ENUM fields and sets default value', async () => {
    const onChange = vi.fn();
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        onChangeFilters: onChange,
      },
    });

    const selects = screen.getByRole('combobox');
    await selects.nth(0).selectOptions('Status');

    // Status enum should default to 'Passed' (first option)
    await expect.element(selects.nth(2)).toHaveValue('Passed');
    await expect.poll(() => onChange).toHaveBeenCalledWith({
      eq: { status: 'Passed' },
    });
  });

  it('emits delete event when delete button is clicked', async () => {
    const onDelete = vi.fn();
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1],
        onDelete,
      },
    });

    await screen.getByRole('button', { name: 'Delete' }).click();
    await expect.poll(() => onDelete).toHaveBeenCalled();
  });

  it('properly handles DATETIME fields', async () => {
    const onChange = vi.fn();
    const screen = await render(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        onChangeFilters: onChange,
      },
    });

    await screen.getByRole('combobox').nth(0).selectOptions('Start Time');

    // DateTimeSelector should emit its default value on mount
    await vi.waitFor(() => {
      const filter = onChange.mock.lastCall[0];
      expect(filter.eq).toHaveProperty('starttime');
      expect(DateTime.fromISO(filter.eq.starttime).isValid).toBe(true);
    });
  });
});
