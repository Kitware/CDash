import { afterEach, describe, expect, it, vi } from 'vitest';
import { getCookie, removeCookie, setCookie } from '../../resources/js/vue/components/shared/legacy/cookies';

describe('cookies', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('reads URI-encoded cookies like jquery.cookie', () => {
    vi.stubGlobal('document', { cookie: 'other=1; cdash_My%20Project_indexNightly_sort=-site%2Cbuildname; quoted="a\\"b"' });
    expect(getCookie('cdash_My Project_indexNightly_sort')).toBe('-site,buildname');
    expect(getCookie('quoted')).toBe('a"b');
    expect(getCookie('missing')).toBeUndefined();
  });

  it('writes URI-encoded cookies without a path', () => {
    const document = { cookie: '' };
    vi.stubGlobal('document', document);
    setCookie('cdash_My Project_indexNightly_sort', ['-site', 'buildname']);
    expect(document.cookie).toBe('cdash_My%20Project_indexNightly_sort=-site%2Cbuildname');

    setCookie('num_builds_per_page', 10, { expires: 365 });
    expect(document.cookie).toMatch(/^num_builds_per_page=10; expires=.* GMT$/);

    removeCookie('cdash_refresh');
    expect(document.cookie).toMatch(/^cdash_refresh=; expires=.* GMT$/);
    expect(Date.parse(document.cookie.split('expires=')[1])).toBeLessThan(Date.now());
  });
});
