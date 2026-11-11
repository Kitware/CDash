import { getCookie } from '../cookies';

/**
 * Splitting the legacy pages' tables into pages.  The number of rows per page is -1 to show every row.
 */

/**
 * The number of rows per page which a cookie remembers.  ItemsPerPage.vue saves it.
 *
 * @param {string} cookie
 * @param {number} defaultNumPerPage Used if the cookie hasn't been saved.
 * @returns {number}
 */
export function savedNumPerPage(cookie, defaultNumPerPage = 50) {
  const numPerPage = parseInt(getCookie(cookie), 10);
  return Number.isNaN(numPerPage) ? defaultNumPerPage : numPerPage;
}

/**
 * @param {number} numRows
 * @param {number} numPerPage
 * @returns {number} At least one page, even without any rows.
 */
export function pageCount(numRows, numPerPage) {
  return numPerPage > 0 ? Math.max(Math.ceil(numRows / numPerPage), 1) : 1;
}

/**
 * The rows on a page.  Past the last page, the rows on the last page.
 *
 * @param {Array} rows
 * @param {number} page Starting from 1.
 * @param {number} numPerPage
 * @returns {Array}
 */
export function pageRows(rows, page, numPerPage) {
  if (numPerPage <= 0) {
    return rows;
  }
  const shownPage = Math.min(page, pageCount(rows.length, numPerPage));
  return rows.slice((shownPage - 1) * numPerPage, shownPage * numPerPage);
}
