import { ref, toValue } from 'vue';
import { getCookie, setCookie } from '../cookies';
import { parseSortFields, sortDirection, updateSortFields } from './sortFields';

/**
 * The order of a table's rows, as angularOrderBy.js predicates, which clicking the column headers
 * changes and a cookie remembers.
 *
 * @param {string|function(): string} cookie The name of the cookie which remembers the order.
 * @param {string[]|null|function(): (string[]|null)} initial The order to start with, or null for the
 *   remembered order.
 */
export function useSortFields(cookie, initial = null) {
  const sortFields = ref(toValue(initial) ?? parseSortFields(getCookie(toValue(cookie))));
  return {
    sortFields,

    /** @returns {'ascending'|'descending'|'none'} How a column is sorted. */
    direction: (field) => sortDirection(sortFields.value, field),

    /** Sort by a column, or by it as well as the current columns if shift was held down. */
    sort(field, append) {
      sortFields.value = updateSortFields(sortFields.value, field, append);
      setCookie(toValue(cookie), sortFields.value);
    },
  };
}
