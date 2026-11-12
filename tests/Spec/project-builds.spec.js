import { describe, expect, it } from 'vitest';
import { showEmptyBuildsLast, showEmptySubProjectsLast } from '../../resources/js/vue/components/ProjectBuildsPage/legacy/showEmptyLast';
import { buildSortCookieName, defaultBuildSort, initialBuildSort } from '../../resources/js/vue/components/ProjectBuildsPage/legacy/buildSorting';
import { pluralizeDays } from '../../resources/js/vue/components/ProjectBuildsPage/pluralizeDays';

const names = (items) => items.map((item) => item.name);

describe('showEmptyBuildsLast', () => {
  const builds = [
    { name: 'missing', expectedandmissing: 1 },
    { name: 'no-build', configure: { error: 0 } },
    { name: 'built', compilation: { error: 1 } },
  ];

  it('shows expected builds which have not submitted last', () => {
    expect(names(showEmptyBuildsLast(builds, ['-builddatefull']))).toEqual(['no-build', 'built', 'missing']);
    expect(names(showEmptyBuildsLast(builds, []))).toEqual(['no-build', 'built', 'missing']);
  });

  it('shows builds without results for the sorted section after the others', () => {
    expect(names(showEmptyBuildsLast(builds, ['-compilation.error', 'site']))).toEqual(['built', 'no-build', 'missing']);
    expect(names(showEmptyBuildsLast(builds, ['configure.error']))).toEqual(['no-build', 'built', 'missing']);
  });
});

describe('showEmptySubProjectsLast', () => {
  const empty = {
    name: 'empty',
    nconfigureerror: 0,
    nconfigurewarning: 0,
    nconfigurepass: 0,
    nbuilderror: 0,
    nbuildwarning: 0,
    nbuildpass: 0,
    ntestfail: 0,
    ntestnotrun: 0,
    ntestpass: 0,
  };
  const subprojects = [
    { ...empty },
    { ...empty, name: 'tested', ntestpass: 3 },
    { ...empty, name: 'built', nbuildpass: 1 },
  ];

  it('shows SubProjects without results for the sorted section last', () => {
    expect(names(showEmptySubProjectsLast(subprojects, ['-nbuildpass']))).toEqual(['built', 'tested', 'empty']);
    expect(names(showEmptySubProjectsLast(subprojects, ['ntestfail']))).toEqual(['tested', 'built', 'empty']);
  });

  it('does not move SubProjects when sorting by name or not sorting', () => {
    expect(names(showEmptySubProjectsLast(subprojects, ['name']))).toEqual(['empty', 'tested', 'built']);
    expect(names(showEmptySubProjectsLast(subprojects, []))).toEqual(['empty', 'tested', 'built']);
  });
});

describe('build sorting', () => {
  it('names the cookie which remembers the order', () => {
    expect(buildSortCookieName('CDash', false, 'Nightly')).toBe('cdash_CDash_indexNightly_sort');
    expect(buildSortCookieName('CDash', true, 'Nightly')).toBe('cdash_CDash_child_indexNightly_sort');
  });

  it('shows the most important problems first', () => {
    const group = {
      numconfigureerror: 1,
      numbuilderror: 1,
      numtestfail: 0,
      numtestnotrun: 1,
      numconfigurewarning: 0,
      numbuildwarning: 1,
    };
    expect(defaultBuildSort(group, false))
      .toEqual(['-configure.error', '-compilation.error', '-test.notrun', '-compilation.warning', '-builddatefull']);
    expect(defaultBuildSort({}, false)).toEqual(['-builddatefull']);
  });

  it('shows continuous builds from newest to oldest', () => {
    expect(defaultBuildSort({ sorttype: 'time', numbuilderror: 1 }, false)).toEqual(['-builddatefull']);
  });

  it('shows the children with problems first, then from oldest to newest', () => {
    expect(defaultBuildSort({ numbuilderror: 1, numconfigureerror: 1, numtestfail: 1 }, true))
      .toEqual(['-compilation.error', '-test.fail', 'builddatefull']);
    expect(defaultBuildSort({ numconfigureerror: 1 }, true)).toEqual(['-configure.error', 'builddatefull']);
  });

  it('prefers the order in the URL, then the saved order', () => {
    const group = { numbuilderror: 1 };
    expect(initialBuildSort({ group, childView: false, urlSort: 'site,-buildname', cookie: 'label' }))
      .toEqual(['site', '-buildname']);
    expect(initialBuildSort({ group, childView: false, urlSort: null, cookie: 'label' })).toEqual(['label']);
    expect(initialBuildSort({ group, childView: false, urlSort: null, cookie: undefined }))
      .toEqual(['-compilation.error', '-builddatefull']);
  });
});

describe('pluralizeDays', () => {
  it('counts days', () => {
    expect(pluralizeDays(1)).toBe('1 day');
    expect(pluralizeDays(3)).toBe('3 days');
    expect(pluralizeDays(0)).toBe('0 days');
    expect(pluralizeDays(0, 'today')).toBe('today');
  });
});
