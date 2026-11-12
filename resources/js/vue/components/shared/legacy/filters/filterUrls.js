/**
 * URL handling for the legacy filters.  The filters are encoded in the query string as
 * "filtercount=2&field1=...&compare1=..." and so on, which the legacy APIs parse.
 *
 * Remove this once the pages which use it use the GraphQL filters.
 */

/**
 * The filter parameters of a URL, which the index API repeats in the links it returns.  The date and
 * limit are left out, since the links set their own.
 *
 * @param {string} href
 * @returns {string} e.g. "&filtercount=1&showfilters=1&field1=..."
 */
export function filterString(href) {
  const idx = href.indexOf('&filtercount=');
  if (idx <= 0) {
    return '';
  }
  return href.substring(idx).replace(/&date=[^&]*/, '').replace(/&limit=[^&]*/, '');
}

/**
 * The URL which shows or hides the filters, if the current URL says whether to show them.
 *
 * @param {string} href
 * @param {boolean} show
 * @returns {string|null} null if the filters can be shown or hidden without reloading the page.
 */
export function showFiltersUrl(href, show) {
  if (href.indexOf('&showfilters=') <= 0) {
    return null;
  }
  return show ? href.replace('&showfilters=0', '&showfilters=1') : href.replace('&showfilters=1', '&showfilters=0');
}

function filterParams(prefix, filter, i) {
  return `&${prefix}field${i}=${encodeURIComponent(filter.field)}`
    + `&${prefix}compare${i}=${encodeURIComponent(filter.compare)}`
    + `&${prefix}value${i}=${encodeURIComponent(filter.value)}`;
}

/**
 * The URL which applies a set of filters and dates to the current page.
 *
 * @param {Object} options
 * @param {{origin: string, pathname: string, search: string}} options.location The current page's location.
 * @param {Object[]} options.filters The filters.  A filter block is an object with its own `filters`.
 * @param {string} options.filtercombine 'and' or 'or'.
 * @param {string|undefined} options.limit The maximum number of rows, or 0 for no limit.
 * @param {string|undefined} options.begin The first date, e.g. "2025-01-15".
 * @param {string|undefined} options.end The last date.
 * @returns {string}
 */
export function filtersUrl({ location, filters, filtercombine, limit, begin, end }) {
  // Keep the parameters which aren't about filters or dates.
  const replacedParams = ['filtercount', 'filtercombine', 'showfilters', 'field', 'compare', 'value', 'limit', 'date', 'begin', 'end'];
  const params = location.search.replace('?', '').split(/[&;]/g)
    .filter((param) => !replacedParams.some((replaced) => param.startsWith(replaced)));

  let url = `${location.origin}${location.pathname}?`;
  for (const param of params) {
    url += `${param}&`;
  }

  if (begin && end) {
    url += begin === end ? `date=${begin}&` : `begin=${begin}&end=${end}&`;
  } else if (begin || end) {
    url += `date=${begin || end}&`;
  }

  url += `filtercount=${filters.length}&showfilters=1`;

  // Compare the input's value loosely with 0, so an empty limit is left out too.
  if (limit === undefined || limit === null || Number(limit) !== 0) {
    url += `&limit=${limit}`;
  }

  if (filters.length > 1) {
    url += `&filtercombine=${filtercombine}`;
  }

  filters.forEach((filter, index) => {
    const i = index + 1;
    if ('filters' in filter) {
      if (filter.filters.length < 1) {
        return;
      }
      url += `&field${i}=block&field${i}count=${filter.filters.length}`;
      filter.filters.forEach((subfilter, j) => {
        url += filterParams(`field${i}`, subfilter, j + 1);
      });
    } else {
      url += filterParams('', filter, i);
    }
  });

  return url;
}

/**
 * The URL of the current page on another date, as chosen with the header's calendar.  The date is
 * placed before any filters.
 *
 * @param {string} href
 * @param {string} date e.g. "2025-01-15"
 * @returns {string}
 */
export function dateUrl(href, date) {
  const url = href
    .replace(/(&begin=)[^&]+/, '')
    .replace(/(&end=)[^&]+/, '')
    .replace(/(&date=)[^&]+/, '');

  const filterIdx = url.indexOf('&filter');
  if (filterIdx > -1) {
    return `${url.slice(0, filterIdx)}&date=${date}${url.slice(filterIdx)}`;
  }
  return `${url}&date=${date}`;
}
