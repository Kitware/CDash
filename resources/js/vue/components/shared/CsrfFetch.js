/**
 * The current CSRF token, from the XSRF-TOKEN cookie. Laravel sets this cookie on every web page,
 * and on API and GraphQL responses to logged-in users, including rejections for a stale token.
 * So unlike the csrf-token meta tag, it stays current when the token changes after the page has
 * loaded, e.g., when the user logs in again in another tab.
 *
 * @returns {string}
 */
function xsrfToken() {
  const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
  return cookie === undefined ? '' : decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

/**
 * A fetch() that sends the current CSRF token. A 419 response means the token was stale, and
 * it sets a fresh XSRF-TOKEN cookie, so the request is retried once. The server rejects such
 * requests before doing anything, so retrying is safe.
 *
 * @param {RequestInfo} url
 * @param {RequestInit} options
 * @returns {Promise<Response>}
 */
export async function fetchWithCsrfToken(url, options = {}) {
  const send = () => {
    const headers = new Headers(options.headers);
    headers.set('X-XSRF-TOKEN', xsrfToken());
    return fetch(url, { ...options, headers });
  };

  const response = await send();
  return response.status === 419 ? send() : response;
}
