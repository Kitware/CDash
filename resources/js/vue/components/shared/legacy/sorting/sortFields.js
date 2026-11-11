/**
 * The legacy pages describe how each table is sorted with a list of angularOrderBy.js predicates, e.g.
 * ['-compilation.error', 'buildname'].  The lists are saved in cookies, so the format is kept until the
 * pages' data comes from GraphQL.
 */

/**
 * The sort order after a user clicks a column header.  Columns are sorted in descending order first,
 * and clicking a sorted column reverses it.  Shift-clicking adds the column to the existing sort order
 * instead of replacing it.
 *
 * @param {string[]} fields The current sort order.
 * @param {string} field The column's property.
 * @param {boolean} append Whether the user held down shift.
 * @returns {string[]} The new sort order.
 */
export function updateSortFields(fields, field, append) {
  const descending = `-${field}`;
  const isDescending = fields.includes(descending);

  if (!append) {
    return [isDescending ? field : descending];
  }

  if (isDescending) {
    return fields.map((f) => (f === descending ? field : f));
  }
  if (fields.includes(field)) {
    return fields.map((f) => (f === field ? descending : f));
  }
  return [...fields, descending];
}

/**
 * How a column is currently sorted.
 *
 * @param {string[]} fields The current sort order.
 * @param {string} field The column's property.
 * @returns {'ascending'|'descending'|'none'}
 */
export function sortDirection(fields, field) {
  if (fields.includes(`-${field}`)) {
    return 'descending';
  }
  if (fields.includes(field)) {
    return 'ascending';
  }
  return 'none';
}

/**
 * Parse a sort order saved in a cookie or given in the URL.
 *
 * @param {string|undefined|null} value e.g. "-compilation.error,buildname"
 * @returns {string[]}
 */
export function parseSortFields(value) {
  return value ? value.split(',') : [];
}

/**
 * The order to show a table's rows in when the page loads: the order given in the URL, the order the
 * user last chose, or the default order.
 *
 * @param {Object} options
 * @param {string|null|undefined} options.urlSort The page's `sort` parameter, e.g. "-compilation.error,buildname".
 * @param {string|undefined} options.cookie The saved order.
 * @param {string[]} options.defaultSort
 * @returns {string[]}
 */
export function initialSortFields({ urlSort, cookie, defaultSort }) {
  if (urlSort) {
    return parseSortFields(urlSort);
  }
  if (cookie) {
    return parseSortFields(cookie);
  }
  return defaultSort;
}
