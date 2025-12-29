import DateTimeSelector from '../../../resources/js/vue/components/shared/DateTimeSelector.vue';
import { DateTime } from 'luxon';

describe('DateTimeSelector', () => {
  it('renders with the correct initial values', () => {
    const initialDate = DateTime.fromObject({
      year: 2025,
      month: 12,
      day: 29,
      hour: 14,
      minute: 30,
      second: 45,
    }, { zone: 'America/New_York' });

    cy.mount(DateTimeSelector, {
      props: {
        modelValue: initialDate,
      },
    });

    cy.get('select').eq(0).should('have.value', '12');   // Month
    cy.get('select').eq(1).should('have.value', '29');   // Day
    cy.get('select').eq(2).should('have.value', '2025'); // Year
    cy.get('select').eq(3).should('have.value', '14');   // Hour
    cy.get('select').eq(4).should('have.value', '30');   // Minute
    cy.get('select').eq(5).should('have.value', '45');   // Second
  });

  it('populates the year dropdown correctly', () => {
    cy.mount(DateTimeSelector);
    const currentYear = new Date().getFullYear();
    cy.get('select').eq(2).find('option').should('have.length', currentYear - 1980 + 1);
    cy.get('select').eq(2).find('option').first().should('have.text', currentYear.toString());
    cy.get('select').eq(2).find('option').last().should('have.text', '1980');
  });

  it('updates the number of days when the month or year changes', () => {
    cy.mount(DateTimeSelector, {
      props: {
        modelValue: DateTime.fromObject({ year: 2023, month: 11, day: 1 }), // November
      },
    });

    // November should have 30 days
    cy.get('select').eq(1).find('option').should('have.length', 30);

    // Change to February 2023 (not a leap year)
    cy.get('select').eq(0).select('Feb');
    cy.get('select').eq(1).find('option').should('have.length', 28);

    // Change to February 2024 (a leap year)
    cy.get('select').eq(2).select('2024');
    cy.get('select').eq(1).find('option').should('have.length', 29);
  });

  it('emits an update:modelValue event with the correct Luxon object on change', () => {
    const initialDate = DateTime.fromObject({
      year: 2025,
      month: 12,
      day: 29,
      hour: 14,
      minute: 30,
      second: 45,
    });

    const onUpdateSpy = cy.spy().as('onUpdateSpy');

    cy.mount(DateTimeSelector, {
      props: {
        modelValue: initialDate,
        'onUpdate:modelValue': onUpdateSpy,
      },
    });

    // Change the hour
    cy.get('select').eq(3).select('15');

    // Check the emitted event
    cy.get('@onUpdateSpy').should((spy) => {
      const newDate = spy.getCall(0).args[0];
      expect(newDate).to.be.an.instanceOf(DateTime);
      expect(newDate.year).to.equal(2025);
      expect(newDate.month).to.equal(12);
      expect(newDate.day).to.equal(29);
      expect(newDate.hour).to.equal(15); // Check for the new value
      expect(newDate.minute).to.equal(30);
      expect(newDate.second).to.equal(45);
    });
  });

  it('populates the timezone dropdown with UTC offsets', () => {
    cy.mount(DateTimeSelector);
    cy.get('select').eq(6).find('option').first().invoke('text').should('match', /UTC[+-]\d{1,2}(:\d{2})?/);
  });

  it('emits an event with the correct timezone on change', () => {
    const initialDate = DateTime.local();
    const onUpdateSpy = cy.spy().as('onUpdateSpy');

    cy.mount(DateTimeSelector, {
      props: {
        modelValue: initialDate,
        'onUpdate:modelValue': onUpdateSpy,
      },
    });

    // Change the timezone
    cy.get('select').eq(6).select('UTC-8');

    // Check the emitted event
    cy.get('@onUpdateSpy').should((spy) => {
      const newDate = spy.getCall(0).args[0];
      expect(newDate).to.be.an.instanceOf(DateTime);
      expect(newDate.offset / 60).to.equal(-8);
    });
  });

  it('does not change other values when timezone is updated', () => {
    const initialDate = DateTime.fromObject({
      year: 2025,
      month: 12,
      day: 29,
      hour: 14,
      minute: 30,
      second: 45,
    }, { zone: 'America/New_York' });

    cy.mount(DateTimeSelector, {
      props: {
        modelValue: initialDate,
      },
    });

    // Change the timezone
    cy.get('select').eq(6).select('UTC-8');

    // Verify other values remain unchanged
    cy.get('select').eq(0).should('have.value', '12');   // Month
    cy.get('select').eq(1).should('have.value', '29');   // Day
    cy.get('select').eq(2).should('have.value', '2025'); // Year
    cy.get('select').eq(3).should('have.value', '14');   // Hour
    cy.get('select').eq(4).should('have.value', '30');   // Minute
    cy.get('select').eq(5).should('have.value', '45');   // Second
  });

  it('clamps the day when changing to a month with fewer days', () => {
    const initialDate = DateTime.fromObject({ year: 2023, month: 3, day: 31 }); // March 31st
    const onUpdateSpy = cy.spy().as('onUpdateSpy');

    cy.mount(DateTimeSelector, {
      props: {
        modelValue: initialDate,
        'onUpdate:modelValue': onUpdateSpy,
      },
    });

    // Change to February
    cy.get('select').eq(0).select('Feb');

    // The day should be clamped to 28, and an event emitted.
    cy.get('select').eq(1).should('have.value', '28');
    cy.get('@onUpdateSpy').should((spy) => {
      const newDate = spy.getCall(0).args[0];
      expect(newDate.day).to.equal(28);
      expect(newDate.month).to.equal(2);
    });
  });

  it('clamps the day when changing to a leap month with fewer days', () => {
    const initialDate = DateTime.fromObject({ year: 2024, month: 3, day: 31 }); // March 31st, 2024 (leap year)
    const onUpdateSpy = cy.spy().as('onUpdateSpy');

    cy.mount(DateTimeSelector, {
      props: {
        modelValue: initialDate,
        'onUpdate:modelValue': onUpdateSpy,
      },
    });

    // Change to February
    cy.get('select').eq(0).select('Feb');

    // The day should be clamped to 29
    cy.get('select').eq(1).should('have.value', '29');
    cy.get('@onUpdateSpy').should((spy) => {
      const newDate = spy.getCall(0).args[0];
      expect(newDate.day).to.equal(29);
      expect(newDate.month).to.equal(2);
    });
  });
});
