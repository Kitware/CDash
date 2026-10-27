import { DateTime } from 'luxon';

/**
 * The nightly time on the same date as a time.
 *
 * @param {DateTime} time
 * @param {string} nightlyTime e.g. "21:00:00"
 * @returns {DateTime}
 */
function atNightlyTime(time, nightlyTime) {
  const { hour, minute, second } = DateTime.fromISO(nightlyTime);
  return time.set({ hour, minute, second, millisecond: 0 });
}

/**
 * A testing day which starts in the afternoon is named after the next day, since most of it falls on that day.
 *
 * @param {string} nightlyTime e.g. "21:00:00"
 */
function namedAfterNextDay(nightlyTime) {
  return DateTime.fromISO(nightlyTime).hour >= 12;
}

/**
 * Testing days start at a project's nightly time, in the nightly time's time zone.  A testing day is
 * identified by its date, as a DateTime at the start of that date in the nightly time's time zone.
 *
 * This is the client-side version of App\Utils\TestingDay and get_dates(), which it is meant to replace.
 */
export default {
  /**
   * The testing day which a time belongs to.
   *
   * @param {DateTime} time
   * @param {string} nightlyTime The time of day when testing days start, e.g. "21:00:00".
   * @param {string} timezone The IANA time zone of the nightly time, e.g. "America/New_York".
   * @returns {DateTime} The testing day's date.
   */
  get(time, nightlyTime, timezone) {
    const localTime = time.setZone(timezone);
    let start = atNightlyTime(localTime, nightlyTime);
    if (start > localTime) {
      start = start.minus({ days: 1 });
    }
    return (namedAfterNextDay(nightlyTime) ? start.plus({ days: 1 }) : start).startOf('day');
  },

  /**
   * The time when a testing day starts.
   *
   * @param {DateTime} date The testing day's date.  Only the calendar date is used.
   * @param {string} nightlyTime The time of day when testing days start, e.g. "21:00:00".
   * @param {string} timezone The IANA time zone of the nightly time, e.g. "America/New_York".
   * @returns {DateTime} The start time, in the nightly time's time zone.
   */
  start(date, nightlyTime, timezone) {
    const day = DateTime.fromObject({ year: date.year, month: date.month, day: date.day }, { zone: timezone });
    return atNightlyTime(namedAfterNextDay(nightlyTime) ? day.minus({ days: 1 }) : day, nightlyTime);
  },
};
