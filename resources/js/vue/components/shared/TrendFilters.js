/**
 * The choices which control which builds a trend chart compares the current build with.  Each chart
 * remembers the user's last choices in the browser's local storage.
 */

import { DateTime } from 'luxon';
import { filterParams } from './legacy/filters/filterUrls';

/**
 * The numbers of builds a trend chart can show.
 */
export const TREND_LIMITS = [25, 50, 100, 200];

/**
 * @typedef {Object} TrendFilters
 * @property {boolean} sameSite Only show builds from the current build's site.
 * @property {boolean} sameName Only show builds with the current build's name.
 * @property {boolean} sameGroup Only show builds in the current build's group.
 * @property {number} limit The maximum number of builds to show, one of TREND_LIMITS.
 */

/**
 * @type {Readonly<TrendFilters>}
 */
export const DEFAULT_TREND_FILTERS = Object.freeze({
  sameSite: true,
  sameName: true,
  sameGroup: true,
  limit: 100,
});

/**
 * @param {string} chart e.g. "build" or "test"
 * @returns {string}
 */
function storageKey(chart) {
  return `cdash.trendFilters.${chart}`;
}

/**
 * The choices the user last made for a chart.  Missing or invalid choices are replaced by the defaults.
 *
 * @param {string} chart e.g. "build" or "test"
 * @returns {TrendFilters}
 */
export function loadTrendFilters(chart) {
  let stored = null;
  try {
    stored = JSON.parse(localStorage.getItem(storageKey(chart)));
  } catch {
    // Local storage is blocked by the browser, or holds something other than JSON.
  }

  const filters = { ...DEFAULT_TREND_FILTERS };
  if (stored !== null && typeof stored === 'object') {
    for (const key of ['sameSite', 'sameName', 'sameGroup']) {
      if (typeof stored[key] === 'boolean') {
        filters[key] = stored[key];
      }
    }
    if (TREND_LIMITS.includes(stored.limit)) {
      filters.limit = stored.limit;
    }
  }
  return filters;
}

/**
 * Remember the user's choices for a chart.  Only the choices which differ from the defaults are kept, so
 * that users get any new defaults for the choices they haven't changed.
 *
 * @param {string} chart e.g. "build" or "test"
 * @param {TrendFilters} filters
 */
export function saveTrendFilters(chart, filters) {
  const changed = Object.fromEntries(Object.keys(DEFAULT_TREND_FILTERS)
    .filter((key) => filters[key] !== DEFAULT_TREND_FILTERS[key])
    .map((key) => [key, filters[key]]));
  try {
    if (Object.keys(changed).length === 0) {
      localStorage.removeItem(storageKey(chart));
    } else {
      localStorage.setItem(storageKey(chart), JSON.stringify(changed));
    }
  } catch {
    // The choices just won't be remembered.
  }
}

/**
 * The conditions which limit a builds query to the builds the user chose to compare with a build.
 *
 * @param {TrendFilters} filters
 * @param {{name: string, buildType: string, siteId: number|string}} build
 * @returns {Object[]} Conditions for a `ProjectBuildsFiltersMultiFilterInput` `all` filter.
 */
export function trendBuildConditions(filters, build) {
  const conditions = [];
  if (filters.sameSite) {
    conditions.push({ has: { site: { eq: { id: build.siteId } } } });
  }
  if (filters.sameName) {
    conditions.push({ eq: { name: build.name } });
  }
  if (filters.sameGroup) {
    conditions.push({ eq: { buildType: build.buildType } });
  }
  return conditions;
}

/**
 * The filter parameters for the index.php and queryTests.php pages which select the same builds as a
 * trend chart: those matching the chosen details of the current build, which started between the
 * oldest plotted build and the current build.  Builds which started in the same second as either of
 * those are included too, even if the chart has no room for them.  The legacy pages match sites by
 * name rather than by id, so they also include builds from other sites with the same name.  Likewise,
 * index.php finds a SubProject by name, so it lists parent builds instead if the SubProject has been
 * removed or its name contains HTML special characters.
 *
 * @param {TrendFilters} filters
 * @param {{name: string, buildType: string, siteName: string, startTime: string}} build
 * @param {string} oldestStartTime The start time of the oldest plotted build.
 * @param {Array<[string, number, string]>} extraFilters Other [field, compare, value] filters.
 * @returns {string} e.g. "filtercount=2&showfilters=1&filtercombine=and&field1=..."
 */
export function trendHistoryFilterParams(filters, build, oldestStartTime, extraFilters = []) {
  const conditions = [...extraFilters];
  if (filters.sameSite) {
    conditions.push(['site', 61, build.siteName]);
  }
  if (filters.sameName) {
    conditions.push(['buildname', 61, build.name]);
  }
  if (filters.sameGroup) {
    conditions.push(['buildtype', 61, build.buildType]);
  }
  // The date comparisons are exclusive, and start times are stored to the second.
  const toISO = (time) => time.toISO({ suppressMilliseconds: true });
  conditions.push(['buildstarttime', 83, toISO(DateTime.fromISO(oldestStartTime, { setZone: true }).minus({ seconds: 1 }))]);
  conditions.push(['buildstarttime', 84, toISO(DateTime.fromISO(build.startTime, { setZone: true }).plus({ seconds: 1 }))]);

  const params = conditions.map(([field, compare, value], index) => filterParams('', { field, compare, value }, index + 1));
  return `filtercount=${conditions.length}&showfilters=1&filtercombine=and${params.join('')}`;
}

/**
 * The build details which differ between the builds plotted in a trend chart, and so are needed to
 * tell them apart.
 *
 * @param {Array<{siteName: string, buildName: string, buildType: string}>} dataPoints
 * @returns {Array<{key: string, label: string}>}
 */
export function variedBuildFields(dataPoints) {
  return [
    { key: 'siteName', label: 'Site' },
    { key: 'buildName', label: 'Build name' },
    { key: 'buildType', label: 'Group' },
  ].filter((field) => new Set(dataPoints.map((dataPoint) => dataPoint[field.key])).size > 1);
}
