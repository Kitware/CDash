import { afterEach, describe, expect, it, vi } from 'vitest';
import { fetchWithCsrfToken } from '../../resources/js/vue/components/shared/CsrfFetch';

describe('fetchWithCsrfToken', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('sends the token from the XSRF-TOKEN cookie', async () => {
    vi.stubGlobal('document', { cookie: 'other=1; XSRF-TOKEN=abc%3D%3D' });
    const fetch = vi.fn().mockResolvedValue({ status: 200 });
    vi.stubGlobal('fetch', fetch);

    await fetchWithCsrfToken('/graphql', { method: 'POST', headers: { 'Content-Type': 'application/json' } });

    const [url, { method, headers }] = fetch.mock.calls[0];
    expect([url, method]).toEqual(['/graphql', 'POST']);
    expect(headers.get('Content-Type')).toBe('application/json');
    expect(headers.get('X-XSRF-TOKEN')).toBe('abc==');
  });

  it('keeps headers given as a Headers object', async () => {
    vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=abc' });
    const fetch = vi.fn().mockResolvedValue({ status: 200 });
    vi.stubGlobal('fetch', fetch);

    await fetchWithCsrfToken('/graphql', { headers: new Headers({ 'Content-Type': 'application/json' }) });

    expect(fetch.mock.calls[0][1].headers.get('Content-Type')).toBe('application/json');
  });

  it('retries once with the refreshed token after a 419', async () => {
    const document = { cookie: 'XSRF-TOKEN=old' };
    vi.stubGlobal('document', document);
    const fetch = vi.fn()
      .mockImplementationOnce(async () => {
        document.cookie = 'XSRF-TOKEN=new';
        return { status: 419 };
      })
      .mockResolvedValueOnce({ status: 200 });
    vi.stubGlobal('fetch', fetch);

    const response = await fetchWithCsrfToken('/graphql');

    expect(response.status).toBe(200);
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(fetch.mock.calls[1][1].headers.get('X-XSRF-TOKEN')).toBe('new');
  });

  it('gives up after one retry', async () => {
    vi.stubGlobal('document', { cookie: '' });
    const fetch = vi.fn().mockResolvedValue({ status: 419 });
    vi.stubGlobal('fetch', fetch);

    const response = await fetchWithCsrfToken('/graphql');

    expect(response.status).toBe(419);
    expect(fetch).toHaveBeenCalledTimes(2);
  });
});
