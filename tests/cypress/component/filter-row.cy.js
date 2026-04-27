import FilterRow from '../../../resources/js/vue/components/shared/FilterRow.vue';
import { FilterType, BasicFilterField } from '../../../resources/js/vue/components/shared/Filters/FilterUtils';
import { DateTime } from 'luxon';

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

  it('renders with initial values', () => {
    const initialField = fields[1]; // Build Name (TEXT)
    const initialOperator = 'contains';
    const initialValue = 'my-build';

    cy.mount(FilterRow, {
      props: {
        fields,
        initialField,
        initialOperator,
        initialValue,
      },
    });

    // We can't use .should('have.value', initialField) directly if it's an object
    // FilterRow's select uses :value="field", so it might be [object Object] in the DOM or handled by Vue
    // Let's check the selected option text instead
    cy.get('select').eq(0).find('option:selected').should((el) => {
      expect(el.text().trim()).to.equal(initialField.name);
    });
    cy.get('select').eq(1).should('have.value', initialOperator);
    cy.get('input[type="text"]').should('have.value', initialValue);
  });

  it('updates operators when field changes', () => {
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
      },
    });

    cy.get('select').eq(1).find('option').should('have.length', 3); // eq, ne, contains

    cy.get('select').eq(0).select('Build Time'); // Change to NUMBER
    cy.get('select').eq(1).find('option').should('have.length', 4); // eq, ne, gt, lt
  });

  it('switches value input type based on field type', () => {
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
      },
    });

    cy.get('input[type="text"]').should('exist');

    cy.get('select').eq(0).select('Build Time');
    cy.get('input[type="number"]').should('exist');

    cy.get('select').eq(0).select('Status');
    cy.get('select').eq(2).should('exist'); // Enum dropdown
    cy.get('select').eq(2).find('option').should('have.length', 3);

    cy.get('select').eq(0).select('Start Time');
    // Check if DateTimeSelector exists (it contains many selects)
    cy.get('div.tw-flex-row').find('select').should('have.length.at.least', 3);
  });

  it('emits changeFilters event when value changes', () => {
    const onChangeSpy = cy.spy().as('onChangeSpy');
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        initialOperator: 'eq',
        'onChangeFilters': onChangeSpy,
      },
    });

    cy.get('input[type="text"]').type('new value');
    cy.get('@onChangeSpy').should('have.been.calledWith', {
      eq: { buildname: 'new value' },
    });
  });

  it('emits changeFilters event when operator changes', () => {
    const onChangeSpy = cy.spy().as('onChangeSpy');
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        initialValue: 'test',
        'onChangeFilters': onChangeSpy,
      },
    });

    cy.get('select').eq(1).select('ne');
    cy.get('@onChangeSpy').should('have.been.calledWith', {
      ne: { buildname: 'test' },
    });
  });

  it('emits changeFilters event when field changes', () => {
    const onChangeSpy = cy.spy().as('onChangeSpy');
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        initialValue: 'test',
        'onChangeFilters': onChangeSpy,
      },
    });

    cy.get('select').eq(0).select('Build Time');
    // When field changes, value is reset to '' (for non-enum) and operator to first one (eq)
    cy.get('@onChangeSpy').should('have.been.calledWith', {
      eq: { buildtime: '' },
    });
  });

  it('handles ENUM fields and sets default value', () => {
    const onChangeSpy = cy.spy().as('onChangeSpy');
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        'onChangeFilters': onChangeSpy,
      },
    });

    cy.get('select').eq(0).select('Status');

    // Status enum should default to 'Passed' (first option)
    cy.get('select').eq(2).should('have.value', 'Passed');
    cy.get('@onChangeSpy').should('have.been.calledWith', {
      eq: { status: 'Passed' },
    });
  });

  it('emits delete event when delete button is clicked', () => {
    const onDeleteSpy = cy.spy().as('onDeleteSpy');
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1],
        'onDelete': onDeleteSpy,
      },
    });

    cy.get('button').contains('Delete').click();
    cy.get('@onDeleteSpy').should('have.been.called');
  });

  it('properly handles DATETIME fields', () => {
    const onChangeSpy = cy.spy().as('onChangeSpy');
    cy.mount(FilterRow, {
      props: {
        fields,
        initialField: fields[1], // TEXT
        'onChangeFilters': onChangeSpy,
      },
    });

    cy.get('select').eq(0).select('Start Time');

    // DateTimeSelector should emit its default value on mount
    cy.get('@onChangeSpy').should((spy) => {
      const lastCall = spy.lastCall;
      expect(lastCall.args[0].eq).to.have.property('starttime');
      const emittedValue = lastCall.args[0].eq.starttime;
      expect(DateTime.fromISO(emittedValue).isValid).to.be.true;
    });
  });
});
