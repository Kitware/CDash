import { describe, expect, it } from 'vitest';
import { DateTime } from 'luxon';
import BuildHistogram, { HISTOGRAM_DAYS } from '../../resources/js/vue/components/ProjectHomePage/BuildHistogram';

const ZONE = 'America/New_York';

// Testing days which start at 21:00 are named after the next day.
const NIGHTLY_TIME = '21:00:00';

describe('BuildHistogram', () => {
  describe('windowStart', () => {
    it('starts at the beginning of the first testing day in the window', () => {
      // Daylight saving time started on 2026-03-08, so the window starts at a different UTC offset.
      const now = DateTime.fromISO('2026-03-20T21:30:00', { zone: ZONE });
      expect(BuildHistogram.windowStart(NIGHTLY_TIME, ZONE, now).toISO()).toBe('2026-02-19T21:00:00.000-05:00');
    });

    it('ends with the current testing day', () => {
      const now = DateTime.fromISO('2026-03-20T20:30:00', { zone: ZONE });
      expect(BuildHistogram.windowStart(NIGHTLY_TIME, ZONE, now).toISO()).toBe('2026-02-18T21:00:00.000-05:00');
    });
  });

  describe('queryVariables', () => {
    it('counts builds started in the window by testing day, and only the failing ones', () => {
      const windowStart = DateTime.fromISO('2026-02-19T21:00:00', { zone: ZONE });
      const inWindow = { ge: { startTime: '2026-02-19T21:00:00-05:00' } };

      expect(BuildHistogram.queryVariables(NIGHTLY_TIME, ZONE, windowStart)).toEqual({
        timezone: ZONE,
        dayStart: '21:00:00',
        histogramFilters: inWindow,
        failedHistogramFilters: {
          all: [
            inWindow,
            {
              any: [
                { gt: { configureErrorsCount: 0 } },
                { gt: { buildErrorsCount: 0 } },
                { gt: { failedTestsCount: 0 } },
              ],
            },
          ],
        },
      });
    });
  });

  describe('dailyCounts', () => {
    const windowStart = DateTime.fromISO('2026-02-19T21:00:00', { zone: ZONE });

    function nonEmptyDays(counts) {
      return counts
        .filter(({ passed, failed }) => passed > 0 || failed > 0)
        .map(({ date, passed, failed }) => [date.toISODate(), passed, failed]);
    }

    it('includes every testing day in the window', () => {
      const counts = BuildHistogram.dailyCounts(NIGHTLY_TIME, ZONE, windowStart, [], []);

      expect(counts).toHaveLength(HISTOGRAM_DAYS);
      expect(counts[0].date.toISODate()).toBe('2026-02-20');
      expect(counts[HISTOGRAM_DAYS - 1].date.toISODate()).toBe('2026-03-21');
      expect(counts.every(({ passed, failed }) => passed === 0 && failed === 0)).toBe(true);
    });

    it('subtracts failing builds from all builds on each testing day', () => {
      // Daylight saving time started on 2026-03-08, so the buckets start at two different offsets.
      const counts = BuildHistogram.dailyCounts(
        NIGHTLY_TIME,
        ZONE,
        windowStart,
        [
          { start: '2026-02-19T21:00:00-05:00', count: 3 },
          { start: '2026-03-08T21:00:00-04:00', count: 2 },
          { start: '2026-03-20T21:00:00-04:00', count: 1 },
        ],
        [
          { start: '2026-02-19T21:00:00-05:00', count: 1 },
          { start: '2026-03-20T21:00:00-04:00', count: 1 },
        ],
      );

      expect(nonEmptyDays(counts)).toEqual([
        ['2026-02-20', 2, 1],
        ['2026-03-09', 2, 0],
        ['2026-03-21', 0, 1],
      ]);
    });

    it('never has a negative number of passing builds', () => {
      // A failing build submitted between the two histogram queries is only counted in the second one.
      const counts = BuildHistogram.dailyCounts(
        NIGHTLY_TIME,
        ZONE,
        windowStart,
        [],
        [{ start: '2026-03-20T21:00:00-04:00', count: 1 }],
      );

      expect(counts[HISTOGRAM_DAYS - 1]).toMatchObject({ passed: 0, failed: 1 });
    });
  });
});
