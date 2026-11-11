import { describe, expect, it } from 'vitest';
import { dateUrl, filterString, filtersUrl, showFiltersUrl } from '../../resources/js/vue/components/shared/legacy/filters/filterUrls';

const location = (search) => ({ origin: 'https://cdash.example', pathname: '/index.php', search });

describe('filterString', () => {
  it('keeps the filter parameters, other than the date and limit', () => {
    expect(filterString('https://cdash.example/index.php?project=A&filtercount=1&showfilters=1&date=2025-01-15&limit=5&field1=site'))
      .toBe('&filtercount=1&showfilters=1&field1=site');
    expect(filterString('https://cdash.example/index.php?project=A&date=2025-01-15')).toBe('');
  });
});

describe('showFiltersUrl', () => {
  it('changes the showfilters parameter if there is one', () => {
    expect(showFiltersUrl('https://cdash.example/index.php?project=A&showfilters=1', false))
      .toBe('https://cdash.example/index.php?project=A&showfilters=0');
    expect(showFiltersUrl('https://cdash.example/index.php?project=A&showfilters=0', true))
      .toBe('https://cdash.example/index.php?project=A&showfilters=1');
    expect(showFiltersUrl('https://cdash.example/index.php?project=A', true)).toBeNull();
  });
});

describe('filtersUrl', () => {
  it('replaces the filters and dates and keeps the other parameters', () => {
    const url = filtersUrl({
      location: location('?project=A&date=2025-01-15&filtercount=1&field1=site&compare1=63&value1=x&buildgroup=Nightly'),
      filters: [
        { field: 'buildname', compare: '63', value: 'a b&c' },
        { filters: [{ field: 'site', compare: '61', value: 's' }, { field: 'label', compare: '63', value: 'l' }] },
      ],
      filtercombine: 'or',
      limit: '5',
      begin: '2025-01-13',
      end: '2025-01-15',
    });
    expect(url).toBe('https://cdash.example/index.php?project=A&buildgroup=Nightly&begin=2025-01-13&end=2025-01-15&'
      + 'filtercount=2&showfilters=1&limit=5&filtercombine=or'
      + '&field1=buildname&compare1=63&value1=a%20b%26c'
      + '&field2=block&field2count=2&field2field1=site&field2compare1=61&field2value1=s'
      + '&field2field2=label&field2compare2=63&field2value2=l');
  });

  it('uses a single date when the range is one day or only has one end', () => {
    const base = { location: location('?project=A'), filters: [], filtercombine: 'and', limit: '0' };
    expect(filtersUrl({ ...base, begin: '2025-01-15', end: '2025-01-15' }))
      .toBe('https://cdash.example/index.php?project=A&date=2025-01-15&filtercount=0&showfilters=1');
    expect(filtersUrl({ ...base, begin: '', end: '2025-01-14' }))
      .toBe('https://cdash.example/index.php?project=A&date=2025-01-14&filtercount=0&showfilters=1');
    expect(filtersUrl({ ...base, begin: '', end: '' }))
      .toBe('https://cdash.example/index.php?project=A&filtercount=0&showfilters=1');
  });

  it('leaves out empty filter blocks and a limit of zero', () => {
    const url = filtersUrl({
      location: location(''),
      filters: [{ filters: [] }, { field: 'site', compare: '63', value: 'x' }],
      filtercombine: 'and',
      limit: '',
    });
    expect(url).toBe('https://cdash.example/index.php?&filtercount=2&showfilters=1&filtercombine=and&field2=site&compare2=63&value2=x');
  });
});

describe('dateUrl', () => {
  it('replaces the date before any filters', () => {
    expect(dateUrl('https://cdash.example/index.php?project=A&begin=2025-01-01&end=2025-01-02&filtercount=1&field1=site', '2025-01-15'))
      .toBe('https://cdash.example/index.php?project=A&date=2025-01-15&filtercount=1&field1=site');
    expect(dateUrl('https://cdash.example/index.php?project=A&date=2025-01-01', '2025-01-15'))
      .toBe('https://cdash.example/index.php?project=A&date=2025-01-15');
  });
});
