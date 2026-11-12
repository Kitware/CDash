import { describe, expect, it } from 'vitest';
import { selectedDates } from '../../resources/js/vue/components/shared/legacy/timeline/timelineChart';

describe('selectedDates', () => {
  const timeline = {
    time_to_date: { 100: '2025-01-13', 200: '2025-01-14', 300: '2025-01-15', 400: '2025-01-16' },
  };

  it('names the first and last days of a selection', () => {
    expect(selectedDates(timeline, [100, 300])).toEqual({ begin: '2025-01-13', end: '2025-01-14' });
    expect(selectedDates(timeline, [200, 300])).toEqual({ begin: '2025-01-14', end: '2025-01-14' });
  });

  it('falls back to the first and last days', () => {
    expect(selectedDates(timeline, [150, 350])).toEqual({ begin: '2025-01-13', end: '2025-01-16' });
  });
});
