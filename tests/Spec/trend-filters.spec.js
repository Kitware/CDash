import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  DEFAULT_TREND_FILTERS,
  loadTrendFilters,
  saveTrendFilters,
  trendBuildConditions,
  trendHistoryFilterParams,
  variedBuildFields,
} from '../../resources/js/vue/components/shared/TrendFilters';

describe('trend filters', () => {
  let storage;

  beforeEach(() => {
    storage = new Map();
    vi.stubGlobal('localStorage', {
      getItem: (key) => storage.get(key) ?? null,
      setItem: (key, value) => storage.set(key, String(value)),
      removeItem: (key) => storage.delete(key),
    });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('remembers each chart\'s filters separately', () => {
    expect(loadTrendFilters('test')).toEqual(DEFAULT_TREND_FILTERS);

    const filters = { sameSite: false, sameName: true, sameGroup: false, limit: 25 };
    saveTrendFilters('test', filters);
    expect(loadTrendFilters('test')).toEqual(filters);
    expect(loadTrendFilters('build')).toEqual(DEFAULT_TREND_FILTERS);
  });

  it('only remembers the filters which differ from the defaults', () => {
    saveTrendFilters('test', { ...DEFAULT_TREND_FILTERS, limit: 25 });
    expect(JSON.parse(storage.get('cdash.trendFilters.test'))).toEqual({ limit: 25 });
    saveTrendFilters('test', { ...DEFAULT_TREND_FILTERS });
    expect(storage.size).toBe(0);
  });

  it('uses the defaults for invalid stored filters', () => {
    storage.set('cdash.trendFilters.test', JSON.stringify({ sameSite: 'no', sameName: false, limit: 30 }));
    expect(loadTrendFilters('test')).toEqual({ ...DEFAULT_TREND_FILTERS, sameName: false });

    for (const value of ['not json', 'null', '7', '[]']) {
      storage.set('cdash.trendFilters.test', value);
      expect(loadTrendFilters('test')).toEqual(DEFAULT_TREND_FILTERS);
    }
  });

  it('uses the defaults when local storage is unavailable', () => {
    const unavailable = () => {
      throw new DOMException('The operation is insecure.', 'SecurityError');
    };
    vi.stubGlobal('localStorage', { getItem: unavailable, setItem: unavailable, removeItem: unavailable });

    expect(() => saveTrendFilters('test', { ...DEFAULT_TREND_FILTERS, limit: 25 })).not.toThrow();
    expect(loadTrendFilters('test')).toEqual(DEFAULT_TREND_FILTERS);
  });

  it('only matches the chosen build details', () => {
    const build = { name: 'Linux', buildType: 'Nightly', siteId: '7' };
    expect(trendBuildConditions(DEFAULT_TREND_FILTERS, build)).toEqual([
      { has: { site: { eq: { id: '7' } } } },
      { eq: { name: 'Linux' } },
      { eq: { buildType: 'Nightly' } },
    ]);
    expect(trendBuildConditions({ ...DEFAULT_TREND_FILTERS, sameSite: false, sameGroup: false }, build)).toEqual([
      { eq: { name: 'Linux' } },
    ]);
  });

  it('filters the history pages to the plotted builds', () => {
    const build = { name: 'Linux gcc', buildType: 'Nightly', siteName: 'a&b', startTime: '2026-11-18T10:00:00-05:00' };
    expect(trendHistoryFilterParams(DEFAULT_TREND_FILTERS, build, '2026-11-01T09:30:00-04:00', [['testname', 61, 'my test']])).toBe(
      'filtercount=6&showfilters=1&filtercombine=and'
      + '&field1=testname&compare1=61&value1=my%20test'
      + '&field2=site&compare2=61&value2=a%26b'
      + '&field3=buildname&compare3=61&value3=Linux%20gcc'
      + '&field4=buildtype&compare4=61&value4=Nightly'
      + '&field5=buildstarttime&compare5=83&value5=2026-11-01T09%3A29%3A59-04%3A00'
      + '&field6=buildstarttime&compare6=84&value6=2026-11-18T10%3A00%3A01-05%3A00',
    );
    // The API's UTC times are like "+00:00".
    expect(trendHistoryFilterParams({ ...DEFAULT_TREND_FILTERS, sameSite: false, sameGroup: false }, build, '2026-11-01T09:30:00+00:00')).toBe(
      'filtercount=3&showfilters=1&filtercombine=and'
      + '&field1=buildname&compare1=61&value1=Linux%20gcc'
      + '&field2=buildstarttime&compare2=83&value2=2026-11-01T09%3A29%3A59Z'
      + '&field3=buildstarttime&compare3=84&value3=2026-11-18T10%3A00%3A01-05%3A00',
    );
  });

  it('lists the build details which differ between data points', () => {
    const point = { siteName: 'a', buildName: 'Linux', buildType: 'Nightly' };
    expect(variedBuildFields([])).toEqual([]);
    expect(variedBuildFields([point, { ...point }])).toEqual([]);
    expect(variedBuildFields([point, { ...point, siteName: 'b', buildType: 'Continuous' }]).map((field) => field.key))
      .toEqual(['siteName', 'buildType']);
  });
});
