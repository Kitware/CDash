import { describe, expect, it } from 'vitest';
import { DateTime } from 'luxon';
import TestingDay from '../../resources/js/vue/components/shared/TestingDay';

describe('TestingDay', () => {
  describe('get', () => {
    it.each([
      // Testing days which start in the morning are named after the same day.
      ['11:59:59', 'UTC', '2019-05-17T11:59:58Z', '2019-05-16'],
      ['11:59:59', 'UTC', '2019-05-17T11:59:59Z', '2019-05-17'],
      // Testing days which start in the afternoon are named after the next day.
      ['12:00:01', 'UTC', '2019-05-17T11:59:59Z', '2019-05-17'],
      ['12:00:01', 'UTC', '2019-05-17T12:00:01Z', '2019-05-18'],
      // The nightly time is in its own time zone.
      ['11:59:59', 'America/New_York', '2019-05-17T15:59:58Z', '2019-05-16'],
      ['11:59:59', 'America/New_York', '2019-05-17T16:00:01Z', '2019-05-17'],
      ['12:00:01', 'America/New_York', '2019-05-17T15:59:59Z', '2019-05-17'],
      ['12:00:01', 'America/New_York', '2019-05-17T16:00:02Z', '2019-05-18'],
      // Daylight saving time started on 2019-03-10 and ended on 2018-11-04 in New York.
      ['01:00:00', 'America/New_York', '2019-03-10T00:59:59-05:00', '2019-03-09'],
      ['01:00:00', 'America/New_York', '2019-03-10T01:00:01-05:00', '2019-03-10'],
      ['01:00:00', 'America/New_York', '2018-11-04T00:59:59-04:00', '2018-11-03'],
      ['01:00:00', 'America/New_York', '2018-11-04T01:00:01-04:00', '2018-11-04'],
      ['04:01:00', 'UTC', '2019-09-26T04:00:59Z', '2019-09-25'],
    ])('with nightly time %s %s, %s is on %s', (nightlyTime, timezone, time, expected) => {
      expect(TestingDay.get(DateTime.fromISO(time), nightlyTime, timezone).toISODate()).toBe(expected);
    });
  });

  describe('start', () => {
    it.each([
      ['01:00:00', 'UTC', '2026-01-02', '2026-01-02T01:00:00.000Z'],
      // Daylight saving time started on 2026-03-08 in New York.
      ['21:00:00', 'America/New_York', '2026-03-08', '2026-03-07T21:00:00.000-05:00'],
      ['21:00:00', 'America/New_York', '2026-03-09', '2026-03-08T21:00:00.000-04:00'],
    ])('with nightly time %s %s, %s starts at %s', (nightlyTime, timezone, date, expected) => {
      expect(TestingDay.start(DateTime.fromISO(date), nightlyTime, timezone).toISO()).toBe(expected);
    });

    it('starts the testing day it names', () => {
      for (const nightlyTime of ['01:00:00', '21:00:00']) {
        const date = TestingDay.get(DateTime.fromISO('2026-03-08T12:00:00Z'), nightlyTime, 'America/New_York');
        const start = TestingDay.start(date, nightlyTime, 'America/New_York');
        expect(TestingDay.get(start, nightlyTime, 'America/New_York').equals(date)).toBe(true);
      }
    });

    it('uses only the calendar date of the testing day', () => {
      // Midnight in Tokyo is still the previous day in New York.
      const date = DateTime.fromISO('2026-03-08T00:00:00', { zone: 'Asia/Tokyo' });
      expect(TestingDay.start(date, '01:00:00', 'America/New_York').toISO()).toBe('2026-03-08T01:00:00.000-05:00');
    });
  });
});
