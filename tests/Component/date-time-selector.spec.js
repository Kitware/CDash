import { DateTime } from 'luxon';
import { describe, expect, it, vi } from 'vitest';
import { render } from 'vitest-browser-vue';
import DateTimeSelector from '../../resources/js/vue/components/shared/DateTimeSelector.vue';

describe('DateTimeSelector', () => {
  const lastEmittedDate = (spy) => DateTime.fromISO(spy.mock.lastCall[0], { setZone: true });

  it('renders with the correct initial values', async () => {
    const initialDate = DateTime.fromObject({
      year: 2025,
      month: 12,
      day: 29,
      hour: 14,
      minute: 30,
      second: 45,
    }, { zone: 'America/New_York' });

    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
      },
    });

    const selects = screen.getByRole('combobox');
    await expect.element(selects.nth(0)).toHaveValue('12'); // Month
    await expect.element(selects.nth(1)).toHaveValue('29'); // Day
    await expect.element(selects.nth(2)).toHaveValue('2025'); // Year
    await expect.element(selects.nth(3)).toHaveValue('14'); // Hour
    await expect.element(selects.nth(4)).toHaveValue('30'); // Minute
    await expect.element(selects.nth(5)).toHaveValue('45'); // Second
  });

  it('populates the year dropdown correctly', async () => {
    const screen = await render(DateTimeSelector);
    const currentYear = new Date().getFullYear();
    const years = screen.getByRole('combobox').nth(2).getByRole('option');
    await expect.element(years).toHaveLength(currentYear - 1980 + 1);
    await expect.element(years.first()).toHaveTextContent(currentYear.toString());
    await expect.element(years.last()).toHaveTextContent('1980');
  });

  it('updates the number of days when the month or year changes', async () => {
    const initialDate = DateTime.fromObject({ year: 2023, month: 11, day: 1 });
    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
      },
    });

    const selects = screen.getByRole('combobox');
    const days = selects.nth(1).getByRole('option');

    // November should have 30 days
    await expect.element(days).toHaveLength(30);

    // Change to February 2023 (not a leap year)
    await selects.nth(0).selectOptions('Feb');
    await expect.element(days).toHaveLength(28);

    // Change to February 2024 (a leap year)
    await selects.nth(2).selectOptions('2024');
    await expect.element(days).toHaveLength(29);
  });

  it('emits an update:modelValue event with the correct ISO string on change', async () => {
    const initialDate = DateTime.fromObject({
      year: 2025,
      month: 12,
      day: 29,
      hour: 14,
      minute: 30,
      second: 45,
    });

    const onUpdate = vi.fn();

    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
        'onUpdate:modelValue': onUpdate,
      },
    });

    // Reset the spy so we only catch the hour change
    onUpdate.mockClear();

    // Change the hour
    await screen.getByRole('combobox').nth(3).selectOptions('15');

    // Check the emitted event
    await vi.waitFor(() => {
      const newDate = lastEmittedDate(onUpdate);
      expect(newDate.hour).toBe(15);
      expect(newDate.year).toBe(2025);
      expect(newDate.month).toBe(12);
      expect(newDate.day).toBe(29);
      expect(newDate.minute).toBe(30);
      expect(newDate.second).toBe(45);
    });
  });

  it('populates the timezone dropdown with UTC offsets', async () => {
    const screen = await render(DateTimeSelector);
    const timezones = screen.getByRole('combobox').nth(6).getByRole('option');
    await expect.element(timezones.first()).toMatchTextContent(/UTC[+-]\d{1,2}(:\d{2})?/);
  });

  it('emits an event with the correct timezone on change', async () => {
    const initialDate = DateTime.local();
    const onUpdate = vi.fn();

    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
        'onUpdate:modelValue': onUpdate,
      },
    });

    // Reset the spy to ignore the initial emission
    onUpdate.mockClear();

    // Change the timezone
    await screen.getByRole('combobox').nth(6).selectOptions('UTC-8');

    // Check the emitted event
    await vi.waitFor(() => {
      expect(lastEmittedDate(onUpdate).offset / 60).toBe(-8);
    });
  });

  it('does not change other values when timezone is updated', async () => {
    const initialDate = DateTime.fromObject({
      year: 2025,
      month: 12,
      day: 29,
      hour: 14,
      minute: 30,
      second: 45,
    }, { zone: 'America/New_York' });

    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
      },
    });

    const selects = screen.getByRole('combobox');

    // Change the timezone
    await selects.nth(6).selectOptions('UTC-8');

    // Verify other values remain unchanged
    await expect.element(selects.nth(0)).toHaveValue('12'); // Month
    await expect.element(selects.nth(1)).toHaveValue('29'); // Day
    await expect.element(selects.nth(2)).toHaveValue('2025'); // Year
    await expect.element(selects.nth(3)).toHaveValue('14'); // Hour
    await expect.element(selects.nth(4)).toHaveValue('30'); // Minute
    await expect.element(selects.nth(5)).toHaveValue('45'); // Second
  });

  it('clamps the day when changing to a month with fewer days', async () => {
    const initialDate = DateTime.fromObject({ year: 2023, month: 3, day: 31 }); // March 31st
    const onUpdate = vi.fn();

    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
        'onUpdate:modelValue': onUpdate,
      },
    });

    // Reset the spy to ignore the initial emission
    onUpdate.mockClear();

    const selects = screen.getByRole('combobox');

    // Change to February
    await selects.nth(0).selectOptions('Feb');

    // The day should be clamped to 28, and an event emitted.
    await expect.element(selects.nth(1)).toHaveValue('28');
    await vi.waitFor(() => {
      const newDate = lastEmittedDate(onUpdate);
      expect(newDate.day).toBe(28);
      expect(newDate.month).toBe(2);
    });
  });

  it('clamps the day when changing to a leap month with fewer days', async () => {
    const initialDate = DateTime.fromObject({ year: 2024, month: 3, day: 31 }); // March 31st, 2024 (leap year)
    const onUpdate = vi.fn();

    const screen = await render(DateTimeSelector, {
      props: {
        modelValue: initialDate.toISO({ suppressMilliseconds: true }),
        'onUpdate:modelValue': onUpdate,
      },
    });

    // Reset the spy to ignore the initial emission
    onUpdate.mockClear();

    const selects = screen.getByRole('combobox');

    // Change to February
    await selects.nth(0).selectOptions('Feb');

    // The day should be clamped to 29
    await expect.element(selects.nth(1)).toHaveValue('29');
    await vi.waitFor(() => {
      const newDate = lastEmittedDate(onUpdate);
      expect(newDate.day).toBe(29);
      expect(newDate.month).toBe(2);
    });
  });
});
