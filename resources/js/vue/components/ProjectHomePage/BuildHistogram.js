import { DateTime } from 'luxon';
import TestingDay from '../shared/TestingDay';

/** The number of testing days shown in build histograms, including the current one. */
export const HISTOGRAM_DAYS = 30;

export const PASSED_COLOR = '#5cb85c';
export const FAILED_COLOR = '#d9534f';

export default {
  /**
   * The start of the first testing day shown in build histograms.
   *
   * @param {string} nightlyTime The time of day when testing days start, e.g. "21:00:00".
   * @param {string} timezone The time zone of the nightly time.
   * @param {DateTime} now
   * @returns {DateTime}
   */
  windowStart(nightlyTime, timezone, now = DateTime.now()) {
    const currentDay = TestingDay.get(now, nightlyTime, timezone);
    return TestingDay.start(currentDay.minus({ days: HISTOGRAM_DAYS - 1 }), nightlyTime, timezone);
  },

  /**
   * Query variables which count every build started in the histogram window by testing day, and only
   * the failing ones.  A build fails if it has configure errors, build errors, or failed tests.
   *
   * @param {string} nightlyTime
   * @param {string} timezone
   * @param {DateTime} windowStart
   */
  queryVariables(nightlyTime, timezone, windowStart) {
    const inWindow = {
      ge: {
        startTime: windowStart.toISO({ suppressMilliseconds: true }),
      },
    };

    return {
      timezone,
      dayStart: nightlyTime,
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
    };
  },

  /**
   * Combine histograms of all builds and failing builds into passing and failing counts for every testing
   * day in the window, including days without builds.
   *
   * @param {string} nightlyTime
   * @param {string} timezone
   * @param {DateTime} windowStart
   * @param {Array<{start: string, count: number}>} histogram
   * @param {Array<{start: string, count: number}>} failedHistogram
   * @returns {Array<{date: DateTime, passed: number, failed: number}>}
   */
  dailyCounts(nightlyTime, timezone, windowStart, histogram, failedHistogram) {
    const countsByDate = (buckets) => new Map(buckets.map(({ start, count }) => [
      TestingDay.get(DateTime.fromISO(start), nightlyTime, timezone).toISODate(),
      count,
    ]));
    const totals = countsByDate(histogram);
    const failures = countsByDate(failedHistogram);

    const firstDay = TestingDay.get(windowStart, nightlyTime, timezone);
    return Array.from({ length: HISTOGRAM_DAYS }, (_, i) => {
      const date = firstDay.plus({ days: i });
      const failed = failures.get(date.toISODate()) ?? 0;
      return {
        date,
        // The histograms are counted separately, so a failing build submitted in between is only in failedHistogram.
        passed: Math.max(0, (totals.get(date.toISODate()) ?? 0) - failed),
        failed,
      };
    });
  },
};
