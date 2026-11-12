import { describe, expect, it } from 'vitest';
import orderBy from '../../resources/js/vue/components/shared/legacy/sorting/angularOrderBy';
import { initialSortFields, parseSortFields, sortDirection, updateSortFields } from '../../resources/js/vue/components/shared/legacy/sorting/sortFields';

const names = (items) => items.map((item) => item.name);

describe('orderBy', () => {
  it('sorts by nested properties in either direction', () => {
    const builds = [
      { name: 'a', compilation: { error: 2 } },
      { name: 'b', compilation: { error: 10 } },
      { name: 'c', compilation: { error: 1 } },
    ];
    expect(names(orderBy(builds, ['compilation.error']))).toEqual(['c', 'a', 'b']);
    expect(names(orderBy(builds, ['-compilation.error']))).toEqual(['b', 'a', 'c']);
    expect(names(orderBy(builds, ['+compilation.error']))).toEqual(['c', 'a', 'b']);
  });

  it('sorts by later predicates when earlier ones are equal', () => {
    const builds = [
      { name: 'a', errors: 1, time: 1 },
      { name: 'b', errors: 2, time: 1 },
      { name: 'c', errors: 1, time: 2 },
    ];
    expect(names(orderBy(builds, ['-errors', '-time']))).toEqual(['b', 'c', 'a']);
  });

  it('keeps the original order of ties, even when sorting in descending order', () => {
    const builds = [{ name: 'a', n: 1 }, { name: 'b', n: 1 }, { name: 'c', n: 0 }];
    expect(names(orderBy(builds, ['-n']))).toEqual(['a', 'b', 'c']);
    expect(names(orderBy(builds, []))).toEqual(['a', 'b', 'c']);
  });

  it('compares strings case-insensitively without locale rules', () => {
    const sites = [{ name: 'b' }, { name: 'B2' }, { name: 'a' }, { name: 'Z' }, { name: '_' }];
    expect(names(orderBy(sites, ['name']))).toEqual(['_', 'a', 'b', 'B2', 'Z']);
  });

  it('orders values by type, then null, then undefined', () => {
    const values = [
      { name: 'undefined' },
      { name: 'string', value: 'abc' },
      { name: 'null', value: null },
      { name: 'number', value: 5 },
      { name: 'boolean', value: false },
    ];
    expect(names(orderBy(values, ['value']))).toEqual(['boolean', 'number', 'string', 'null', 'undefined']);
    // Descending sorts reverse all of it.
    expect(names(orderBy(values, ['-value']))).toEqual(['undefined', 'null', 'string', 'number', 'boolean']);
  });

  it('sorts by array elements', () => {
    const tests = [
      { name: 'a', measurements: ['x', 3] },
      { name: 'b', measurements: ['y', 10] },
      { name: 'c', measurements: ['z'] },
    ];
    expect(names(orderBy(tests, ['measurements[1]']))).toEqual(['a', 'b', 'c']);
    expect(names(orderBy(tests, ['-measurements[1]']))).toEqual(['c', 'b', 'a']);
  });

  it('does not change its input', () => {
    const builds = [{ name: 'b' }, { name: 'a' }];
    orderBy(builds, ['name']);
    expect(names(builds)).toEqual(['b', 'a']);
  });
});

describe('updateSortFields', () => {
  it('sorts by one column, descending first', () => {
    expect(updateSortFields(['-site', 'buildname'], 'label', false)).toEqual(['-label']);
    expect(updateSortFields(['-label'], 'label', false)).toEqual(['label']);
    expect(updateSortFields(['label'], 'label', false)).toEqual(['-label']);
  });

  it('adds columns or reverses them when shift-clicked', () => {
    expect(updateSortFields(['-site'], 'buildname', true)).toEqual(['-site', '-buildname']);
    expect(updateSortFields(['-site', '-buildname'], 'site', true)).toEqual(['site', '-buildname']);
    expect(updateSortFields(['site', '-buildname'], 'site', true)).toEqual(['-site', '-buildname']);
  });

  it('describes how a column is sorted', () => {
    expect(sortDirection(['-site', 'buildname'], 'site')).toBe('descending');
    expect(sortDirection(['-site', 'buildname'], 'buildname')).toBe('ascending');
    expect(sortDirection(['-site', 'buildname'], 'label')).toBe('none');
  });

  it('parses saved sort orders', () => {
    expect(parseSortFields('-compilation.error,buildname')).toEqual(['-compilation.error', 'buildname']);
    expect(parseSortFields('')).toEqual([]);
    expect(parseSortFields(undefined)).toEqual([]);
  });

  it('prefers the order in the URL, then the saved order, then the default', () => {
    expect(initialSortFields({ urlSort: 'site,-buildname', cookie: 'label', defaultSort: ['-time'] }))
      .toEqual(['site', '-buildname']);
    expect(initialSortFields({ urlSort: null, cookie: 'label', defaultSort: ['-time'] })).toEqual(['label']);
    expect(initialSortFields({ urlSort: undefined, cookie: undefined, defaultSort: ['-time'] })).toEqual(['-time']);
  });
});
