import { afterEach, describe, expect, it, vi } from 'vitest';
import { pageCount, pageRows, savedNumPerPage } from '../../resources/js/vue/components/shared/legacy/pagination/paging';

describe('paging', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('counts pages, with at least one', () => {
    expect(pageCount(11, 10)).toBe(2);
    expect(pageCount(10, 10)).toBe(1);
    expect(pageCount(0, 10)).toBe(1);
    expect(pageCount(11, -1)).toBe(1);
  });

  it('shows the rows on a page, or on the last page if it is past the end', () => {
    const rows = [1, 2, 3, 4, 5];
    expect(pageRows(rows, 1, 2)).toEqual([1, 2]);
    expect(pageRows(rows, 3, 2)).toEqual([5]);
    expect(pageRows(rows, 4, 2)).toEqual([5]);
    expect(pageRows(rows, 2, -1)).toEqual(rows);
  });

  it('reads the saved number of rows per page', () => {
    vi.stubGlobal('document', { cookie: 'num_builds_per_page=25; other=x' });
    expect(savedNumPerPage('num_builds_per_page')).toBe(25);
    expect(savedNumPerPage('other')).toBe(50);
    expect(savedNumPerPage('missing', 10)).toBe(10);
  });
});
