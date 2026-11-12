import { initialSortFields } from '../../shared/legacy/sorting/sortFields';

/**
 * How the project builds page sorts each build group's builds when the user hasn't chosen an order.
 */

/**
 * The cookie which remembers how a build group is sorted.  A parent build's children are sorted
 * separately from the builds on a day.
 *
 * @param {string} projectName
 * @param {boolean} childView
 * @param {string} groupName
 * @returns {string}
 */
export function buildSortCookieName(projectName, childView, groupName) {
  return `cdash_${projectName}${childView ? '_child_index' : '_index'}${groupName}_sort`;
}

/**
 * Show the builds with the most important problems first.  A parent build's children are listed from
 * oldest to newest after those with problems, and continuous builds from newest to oldest.
 *
 * @param {Object} group A build group from the index API.
 * @param {boolean} childView Whether the page shows a parent build's children.
 * @returns {string[]}
 */
export function defaultBuildSort(group, childView) {
  const fields = [];
  if (childView) {
    if (group.numbuilderror > 0) {
      fields.push('-compilation.error');
    } else if (group.numconfigureerror > 0) {
      fields.push('-configure.error');
    }
    if (group.numtestfail > 0) {
      fields.push('-test.fail');
    }
    fields.push('builddatefull');
  } else if (!('sorttype' in group)) {
    const priorities = [
      ['numconfigureerror', '-configure.error'],
      ['numbuilderror', '-compilation.error'],
      ['numtestfail', '-test.fail'],
      ['numtestnotrun', '-test.notrun'],
      ['numconfigurewarning', '-configure.warning'],
      ['numbuildwarning', '-compilation.warning'],
    ];
    for (const [count, field] of priorities) {
      if (group[count] > 0) {
        fields.push(field);
      }
    }
    fields.push('-builddatefull');
  } else if (group.sorttype === 'time') {
    fields.push('-builddatefull');
  }
  return fields;
}

/**
 * The order to show a build group's builds in when the page loads: the order given in the URL, the
 * order the user last chose, or the default order.
 *
 * @param {Object} options
 * @param {Object} options.group A build group from the index API.
 * @param {boolean} options.childView
 * @param {string|null} options.urlSort The page's `sort` parameter, e.g. "-compilation.error,buildname".
 * @param {string|undefined} options.cookie The saved order.
 * @returns {string[]}
 */
export function initialBuildSort({ group, childView, urlSort, cookie }) {
  return initialSortFields({ urlSort, cookie, defaultSort: defaultBuildSort(group, childView) });
}
