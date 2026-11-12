/**
 * A number of days, e.g. "1 day" or "3 days".
 *
 * @param {number} count
 * @param {string|null} zero What to say for zero days, if not "0 days".
 * @returns {string}
 */
export function pluralizeDays(count, zero = null) {
  const days = Number(count);
  if (days === 0 && zero !== null) {
    return zero;
  }
  return days === 1 ? '1 day' : `${count} days`;
}
